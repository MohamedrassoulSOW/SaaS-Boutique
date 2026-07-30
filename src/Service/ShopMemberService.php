<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\ShopMember;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ShopMemberService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private ActivityLogger $activityLogger,
        private AppMailer $appMailer,
    ) {
    }

    /**
     * @param array{
     *   firstName: string,
     *   lastName: string,
     *   email: string,
     *   phone?: ?string,
     *   plainPassword?: ?string
     * } $profile
     */
    public function create(Shop $shop, User $merchant, array $profile, string $role): ShopMember
    {
        $email = strtolower(trim($profile['email']));
        if ($this->userRepository->findOneBy(['email' => $email])) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé.');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName(trim($profile['firstName']));
        $user->setLastName(trim($profile['lastName']));
        $user->setPhone($profile['phone'] ?? null);
        $user->setRoles([User::ROLE_EMPLOYEE]);
        $user->setIsActive(true);
        $user->setPreferredShopId($shop->getId());

        $password = (string) ($profile['plainPassword'] ?? '');
        if ($password === '') {
            throw new \InvalidArgumentException('Mot de passe obligatoire.');
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $member = new ShopMember();
        $member->setShop($shop);
        $member->setUser($user);
        $member->setRole($role);
        $member->setIsActive(true);

        $this->em->persist($user);
        $this->em->persist($member);
        $this->em->flush();

        $this->activityLogger->log(
            'staff.create',
            sprintf('Accès vendeur créé : %s (%s)', $user->getFullName(), $user->getEmail()),
            $merchant,
            $shop
        );

        $this->appMailer->sendWelcomeStaff($user, $password, $shop);

        return $member;
    }

    /**
     * @param array{
     *   firstName: string,
     *   lastName: string,
     *   email: string,
     *   phone?: ?string,
     *   plainPassword?: ?string
     * } $profile
     */
    public function update(ShopMember $member, User $merchant, array $profile, string $role, bool $isActive): void
    {
        $user = $member->getUser();
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur introuvable.');
        }

        $email = strtolower(trim($profile['email']));
        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing && $existing->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé.');
        }

        $user->setEmail($email);
        $user->setFirstName(trim($profile['firstName']));
        $user->setLastName(trim($profile['lastName']));
        $user->setPhone($profile['phone'] ?? null);
        $user->setIsActive($isActive);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $password = $profile['plainPassword'] ?? null;
        if (\is_string($password) && $password !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        $member->setRole($role);
        $member->setIsActive($isActive);

        $this->em->flush();

        $this->activityLogger->log(
            'staff.update',
            sprintf('Accès vendeur modifié : %s', $user->getEmail()),
            $merchant,
            $member->getShop()
        );

        if (\is_string($password) && $password !== '') {
            $this->appMailer->sendPasswordChanged($user, $password);
        }
    }

    public function delete(ShopMember $member, User $merchant): void
    {
        $shop = $member->getShop();
        $user = $member->getUser();
        $label = $user?->getEmail() ?? 'inconnu';

        $this->em->remove($member);

        if ($user && $user->isEmployee() && $user->getShopMemberships()->count() <= 1) {
            $this->em->remove($user);
        }

        $this->em->flush();

        $this->activityLogger->log(
            'staff.delete',
            sprintf('Accès vendeur supprimé : %s', $label),
            $merchant,
            $shop
        );
    }
}
