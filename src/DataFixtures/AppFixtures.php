<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Merchant;
use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\Subscription;
use App\Entity\Supplier;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@boutiquesaas.test');
        $admin->setFirstName('Admin');
        $admin->setLastName('Plateforme');
        $admin->setRoles([User::ROLE_ADMIN]);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $merchantUser = new User();
        $merchantUser->setEmail('commercant@demo.test');
        $merchantUser->setFirstName('Amina');
        $merchantUser->setLastName('Diallo');
        $merchantUser->setPhone('+221770000000');
        $merchantUser->setRoles([User::ROLE_MERCHANT]);
        $merchantUser->setPassword($this->hasher->hashPassword($merchantUser, 'demo1234'));

        $merchant = new Merchant();
        $merchant->setCompanyName('Diallo Commerce SARL');
        $merchant->setCity('Dakar');
        $merchant->setCountry('Sénégal');
        $merchant->setUser($merchantUser);
        $merchantUser->setMerchant($merchant);

        $subscription = new Subscription();
        $subscription->setMerchant($merchant);
        $subscription->setPlan(Subscription::PLAN_PRO);
        $subscription->setPrice('49.99');
        $merchant->setSubscription($subscription);

        $shop = new Shop();
        $shop->setMerchant($merchant);
        $shop->setName('Boutique Plateau');
        $shop->setAddress('12 Avenue de la République');
        $shop->setPhone('+221338000000');
        $shop->setEmail('plateau@demo.test');
        $merchant->addShop($shop);

        $catFood = (new Category())->setShop($shop)->setName('Alimentation')->setSortOrder(1);
        $catHygiene = (new Category())->setShop($shop)->setName('Hygiène')->setSortOrder(2);

        $products = [
            ['Riz 25kg', 'RIZ25', '100', '125', 40, $catFood],
            ['Huile 5L', 'HUILE5', '45', '55', 25, $catFood],
            ['Savon', 'SAVON1', '1.5', '2.5', 8, $catHygiene],
            ['Dentifrice', 'DENT1', '3', '5', 3, $catHygiene],
        ];

        foreach ($products as [$name, $ref, $buy, $sell, $qty, $cat]) {
            $p = new Product();
            $p->setShop($shop);
            $p->setCategory($cat);
            $p->setName($name);
            $p->setReference($ref);
            $p->setBarcode('BC'.$ref);
            $p->setPurchasePrice(number_format((float) $buy, 2, '.', ''));
            $p->setSalePrice(number_format((float) $sell, 2, '.', ''));
            $p->setQuantity($qty);
            $p->setMinStock(5);
            $manager->persist($p);
        }

        $supplier = (new Supplier())
            ->setShop($shop)
            ->setName('Grossiste Central')
            ->setPhone('+221770111111')
            ->setEmail('grossiste@demo.test');

        $customer = (new Customer())
            ->setShop($shop)
            ->setFirstName('Moussa')
            ->setLastName('Ndiaye')
            ->setPhone('+221770222222');

        $manager->persist($admin);
        $manager->persist($merchantUser);
        $manager->persist($merchant);
        $manager->persist($subscription);
        $manager->persist($shop);
        $manager->persist($catFood);
        $manager->persist($catHygiene);
        $manager->persist($supplier);
        $manager->persist($customer);
        $manager->flush();
    }
}
