<?php

namespace App\Command;

use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\User;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Service\ContractService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:contracts:backfill', description: 'Crée un contrat pour chaque boutique qui n\'en a pas encore')]
class BackfillContractsCommand extends Command
{
    public function __construct(
        private ShopRepository $shops,
        private UserRepository $users,
        private ContractService $contracts,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var User|null $admin */
        $admin = $this->users->findOneBy(['email' => 'admin@boutiquesaas.test'])
            ?: $this->users->createQueryBuilder('u')
                ->andWhere('u.roles LIKE :r')->setParameter('r', '%ROLE_ADMIN%')
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if (!$admin) {
            $io->error('Aucun admin trouvé pour rattacher les contrats.');

            return Command::FAILURE;
        }

        $created = 0;
        /** @var Shop[] $all */
        $all = $this->shops->findAll();
        foreach ($all as $shop) {
            if ($shop->getContract()) {
                continue;
            }

            $merchant = $shop->getMerchant();
            if (!$merchant) {
                continue;
            }

            $sub = $merchant->getSubscription();
            $plan = $sub?->getPlan() ?: 'basic';
            $price = $sub?->getPrice() ?: '15000';
            $billing = $sub?->getBillingPeriod() ?: ShopContract::BILLING_MONTHLY;

            $this->contracts->createForShop(
                $shop,
                $admin,
                $plan === 'free' ? 'basic' : $plan,
                $price === '0.00' || $price === '0' ? '15000' : $price,
                12,
                $billing,
            );
            ++$created;
            $io->writeln(sprintf('  + %s → contrat créé', $shop->getName()));
        }

        $io->success(sprintf('%d contrat(s) créé(s). Total en base : %d', $created, $this->em->getRepository(ShopContract::class)->count([])));

        return Command::SUCCESS;
    }
}
