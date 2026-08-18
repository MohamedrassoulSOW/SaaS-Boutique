<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Merchant;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\ShopMember;
use App\Entity\StockMovement;
use App\Entity\Subscription;
use App\Entity\Supplier;
use App\Entity\User;
use App\Service\ProductPhotoGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private ProductPhotoGenerator $photoGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@boutiquesaas.test');
        $admin->setFirstName('Admin');
        $admin->setLastName('Plateforme');
        $admin->setPhone('+221770000001');
        $admin->setRoles([User::ROLE_ADMIN]);
        $admin->setPassword($this->hasher->hashPassword($admin, bin2hex(random_bytes(16))));
        $manager->persist($admin);

        $merchantsData = [
            [
                'email' => 'entrepreneur@demo.test',
                'password' => bin2hex(random_bytes(16)),
                'firstName' => 'Amina',
                'lastName' => 'Diallo',
                'phone' => '+221770000010',
                'company' => 'Diallo Commerce SARL',
                'taxId' => 'SN-DKR-001',
                'city' => 'Dakar',
                'shop' => [
                    'name' => 'Entreprise Plateau',
                    'address' => '12 Avenue de la République, Plateau',
                    'phone' => '+221338001001',
                    'email' => 'plateau@demo.test',
                    'categories' => ['Alimentation', 'Boissons', 'Hygiène'],
                    'products' => [
                        ['Riz parfumé 25kg', 'RIZ25', '8712345000011', 'Caprice', 'Alimentation', '9800', '12500', 80, 8],
                        ['Huile végétale 5L', 'HUILE5', '8712345000012', 'Dinor', 'Alimentation', '4500', '5800', 60, 6],
                        ['Sucre 1kg', 'SUCRE1', '8712345000013', 'CSS', 'Alimentation', '650', '900', 100, 15],
                        ['Eau minérale 1.5L', 'EAU15', '8712345000014', 'Kirène', 'Boissons', '250', '400', 200, 24],
                        ['Jus bissap 1L', 'JUS1', '8712345000015', 'Maison', 'Boissons', '700', '1200', 70, 10],
                        ['Savon de Marseille', 'SAVON1', '8712345000016', 'Lux', 'Hygiène', '350', '600', 50, 8],
                        ['Dentifrice 75ml', 'DENT1', '8712345000017', 'Colgate', 'Hygiène', '900', '1500', 40, 6],
                        ['Lessive 2kg', 'LESS2', '8712345000018', 'Omo', 'Hygiène', '2800', '3900', 45, 5],
                    ],
                    'suppliers' => [['Grossiste Central', '+221770111111', 'grossiste@demo.test', 'Sandaga']],
                    'customers' => [
                        ['Moussa', 'Ndiaye', '+221770222201', 'moussa@email.test', 'Médina'],
                        ['Fatou', 'Ba', '+221770222202', 'fatou@email.test', 'Ouakam'],
                        ['Ibrahima', 'Sarr', '+221770222203', null, 'Parcelles'],
                    ],
                ],
            ],
            [
                'email' => 'almadies@demo.test',
                'password' => bin2hex(random_bytes(16)),
                'firstName' => 'Omar',
                'lastName' => 'Sy',
                'phone' => '+221770000020',
                'company' => 'Sy Almadies Trading',
                'taxId' => 'SN-DKR-002',
                'city' => 'Dakar',
                'shop' => [
                    'name' => 'Entreprise Almadies',
                    'address' => 'Route des Almadies, près du phare',
                    'phone' => '+221338001002',
                    'email' => 'boutique.almadies@demo.test',
                    'categories' => ['Épicerie', 'Produits frais', 'Maison'],
                    'products' => [
                        ['Lait en poudre 400g', 'LAIT400', '8712345000021', 'Nido', 'Épicerie', '3200', '4200', 50, 8],
                        ['Café moulu 250g', 'CAFE250', '8712345000022', 'Café Touba', 'Épicerie', '1500', '2200', 40, 6],
                        ['Pâtes 500g', 'PATE500', '8712345000023', 'Panzani', 'Épicerie', '700', '1100', 60, 12],
                        ['Tomates fraîches 1kg', 'TOM1', '8712345000024', 'Local', 'Produits frais', '400', '800', 35, 5],
                        ['Oignons 1kg', 'OIG1', '8712345000025', 'Local', 'Produits frais', '350', '650', 40, 5],
                        ['Poulet entier', 'POUL1', '8712345000026', 'Sedima', 'Produits frais', '3500', '4800', 30, 4],
                        ['Balai', 'BALAI1', '8712345000027', 'Maison+', 'Maison', '1200', '2000', 25, 3],
                        ['Seau 10L', 'SEAU10', '8712345000028', 'Maison+', 'Maison', '900', '1600', 28, 4],
                    ],
                    'suppliers' => [['Ferme Sedima', '+221770222110', 'sedima@demo.test', 'Sangalkam']],
                    'customers' => [
                        ['Aïssatou', 'Fall', '+221770333301', 'aissatou@email.test', 'Ngor'],
                        ['Cheikh', 'Diop', '+221770333302', null, 'Yoff'],
                    ],
                ],
            ],
            [
                'email' => 'guediawaye@demo.test',
                'password' => bin2hex(random_bytes(16)),
                'firstName' => 'Khady',
                'lastName' => 'Ba',
                'phone' => '+221770000030',
                'company' => 'Ba Distribution',
                'taxId' => 'SN-GDW-003',
                'city' => 'Guédiawaye',
                'shop' => [
                    'name' => 'Entreprise Guédiawaye',
                    'address' => 'Marché de Guédiawaye, allée 4',
                    'phone' => '+221338001003',
                    'email' => 'boutique.guediawaye@demo.test',
                    'categories' => ['Céréales', 'Épices', 'Produits ménagers'],
                    'products' => [
                        ['Mil 5kg', 'MIL5', '8712345000031', 'Local', 'Céréales', '2200', '3200', 50, 8],
                        ['Maïs 5kg', 'MAIS5', '8712345000032', 'Local', 'Céréales', '1800', '2700', 45, 8],
                        ['Couscous 1kg', 'COUS1', '8712345000033', 'Ferrero', 'Céréales', '900', '1400', 55, 10],
                        ['Poivre 100g', 'POIV100', '8712345000034', 'Épices Sahel', 'Épices', '500', '900', 35, 6],
                        ['Cube Maggi x60', 'MAG60', '8712345000035', 'Maggi', 'Épices', '1800', '2500', 60, 12],
                        ['Piment sec 100g', 'PIM100', '8712345000036', 'Épices Sahel', 'Épices', '400', '750', 30, 5],
                        ['Javel 1L', 'JAV1', '8712345000037', 'Eau de Javel', 'Produits ménagers', '600', '1000', 40, 8],
                        ['Éponge x3', 'EPON3', '8712345000038', 'Spontex', 'Produits ménagers', '450', '800', 25, 6],
                    ],
                    'suppliers' => [['Coopérative Céréales', '+221770333110', 'cereales@demo.test', 'Thiès']],
                    'customers' => [['Mariama', 'Sow', '+221770444401', 'mariama@email.test', 'Guédiawaye']],
                ],
            ],
            [
                'email' => 'thies@demo.test',
                'password' => bin2hex(random_bytes(16)),
                'firstName' => 'Ibrahima',
                'lastName' => 'Kane',
                'phone' => '+221770000040',
                'company' => 'Kane Tech Boutique',
                'taxId' => 'SN-THS-004',
                'city' => 'Thiès',
                'shop' => [
                    'name' => 'Entreprise Thiès Centre',
                    'address' => 'Rue Malick Sy, Thiès',
                    'phone' => '+221339001004',
                    'email' => 'boutique.thies@demo.test',
                    'categories' => ['Électronique', 'Accessoires', 'Papeterie'],
                    'products' => [
                        ['Chargeur USB-C', 'CHGUSB', '8712345000041', 'Anker', 'Électronique', '3500', '5500', 35, 5],
                        ['Écouteurs Bluetooth', 'EARBT', '8712345000042', 'Oraimo', 'Électronique', '7000', '11000', 25, 3],
                        ['Powerbank 10000mAh', 'PWR10', '8712345000043', 'Baseus', 'Électronique', '8500', '13000', 20, 3],
                        ['Cable HDMI 2m', 'HDMI2', '8712345000044', 'Generic', 'Accessoires', '2500', '4000', 30, 4],
                        ['Support téléphone', 'SUPTEL', '8712345000045', 'Generic', 'Accessoires', '1200', '2200', 35, 6],
                        ['Cahier 200 pages', 'CAH200', '8712345000046', 'Clairefontaine', 'Papeterie', '600', '1000', 90, 20],
                        ['Stylo bic x10', 'BIC10', '8712345000047', 'Bic', 'Papeterie', '800', '1400', 70, 15],
                        ['Clé USB 32Go', 'USB32', '8712345000048', 'Sandisk', 'Électronique', '4500', '7000', 22, 5],
                    ],
                    'suppliers' => [['Tech Import Senegal', '+221770444110', 'tech@import.test', 'Dakar']],
                    'customers' => [['Modou', 'Kane', '+221770555501', 'modou@email.test', 'Thiès Nord']],
                ],
            ],
            [
                'email' => 'mbour@demo.test',
                'password' => bin2hex(random_bytes(16)),
                'firstName' => 'Fatou',
                'lastName' => 'Sarr',
                'phone' => '+221770000050',
                'company' => 'Sarr Plage Commerce',
                'taxId' => 'SN-MBR-005',
                'city' => 'Mbour',
                'shop' => [
                    'name' => 'Entreprise Mbour Plage',
                    'address' => 'Corniche de Mbour, face à la plage',
                    'phone' => '+221339001005',
                    'email' => 'boutique.mbour@demo.test',
                    'categories' => ['Souvenirs', 'Boissons froides', 'Snacks'],
                    'products' => [
                        ['T-shirt souvenir', 'TSHIRT', '8712345000051', 'Mbour Wear', 'Souvenirs', '2500', '4500', 45, 8],
                        ['Bracelet artisanal', 'BRAC1', '8712345000052', 'Artisan Local', 'Souvenirs', '800', '1800', 60, 10],
                        ['Casquette plage', 'CASQ1', '8712345000053', 'SunDay', 'Souvenirs', '1500', '3000', 35, 6],
                        ['Soda cola 33cl', 'COLA33', '8712345000054', 'Coca-Cola', 'Boissons froides', '350', '600', 120, 20],
                        ['Eau gazeuse 50cl', 'GAZ50', '8712345000055', 'Kirène', 'Boissons froides', '300', '500', 100, 15],
                        ['Glace cornet', 'GLACE1', '8712345000056', 'Miko', 'Snacks', '400', '800', 55, 10],
                        ['Chips 50g', 'CHIP50', '8712345000057', 'Lay\'s', 'Snacks', '250', '500', 80, 15],
                        ['Biscuits assortis', 'BISC1', '8712345000058', 'Lu', 'Snacks', '600', '1000', 30, 8],
                    ],
                    'suppliers' => [['Boissons Froid Express', '+221770555110', 'froid@demo.test', 'Mbour']],
                    'customers' => [
                        ['Abdoulaye', 'Faye', '+221770666603', null, 'Mbour Centre'],
                        ['Sophie', 'Martin', '+221770666602', 'sophie@email.test', 'Résidence Plage'],
                    ],
                ],
            ],
        ];

        $paymentMethods = [Sale::PAYMENT_CASH, Sale::PAYMENT_CARD, Sale::PAYMENT_MOBILE, Sale::PAYMENT_CREDIT];

        foreach ($merchantsData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setPhone($data['phone']);
            $user->setRoles([User::ROLE_MERCHANT]);
            $user->setPassword($this->hasher->hashPassword($user, $data['password']));

            $merchant = new Merchant();
            $merchant->setCompanyName($data['company']);
            $merchant->setTaxId($data['taxId']);
            $merchant->setCity($data['city']);
            $merchant->setCountry('Sénégal');
            $merchant->setLegalForm('SARL');
            $merchant->setRepresentativeTitle('Gérant');
            $merchant->setAddress($data['shop']['address'] ?? null);
            $merchant->setUser($user);
            $user->setMerchant($merchant);

            $subscription = new Subscription();
            $subscription->setMerchant($merchant);
            $subscription->setPlan(Subscription::PLAN_PRO);
            $subscription->setPrice('25000');
            $subscription->setStatus(Subscription::STATUS_ACTIVE);
            $merchant->setSubscription($subscription);

            $manager->persist($user);
            $manager->persist($merchant);
            $manager->persist($subscription);

            $shopData = $data['shop'];
            $shop = new Shop();
            $shop->setMerchant($merchant);
            $shop->setName($shopData['name']);
            $shop->setAddress($shopData['address']);
            $shop->setPhone($shopData['phone']);
            $shop->setEmail($shopData['email']);
            $shop->setIsActive(true);
            $merchant->addShop($shop);
            $manager->persist($shop);

            $contract = new ShopContract();
            $contract->setShop($shop);
            $contract->setMerchant($merchant);
            $contract->setCreatedBy($user);
            $contract->setPlan(Subscription::PLAN_PRO);
            $contract->setPrice('25000');
            $contract->setBillingPeriod(ShopContract::BILLING_MONTHLY);
            $contract->setDurationMonths(12);
            $contract->setStartsAt(new \DateTimeImmutable());
            $contract->setEndsAt((new \DateTimeImmutable())->modify('+12 months'));
            $contract->setStatus(ShopContract::STATUS_PENDING);
            $contract->setSharedWithMerchant(true);
            $contract->setProposedShopName($shop->getName());
            $contract->setProposedShopAddress($shop->getAddress());
            $contract->setProposedShopPhone($shop->getPhone());
            $contract->setProposedShopEmail($shop->getEmail());
            $shop->setContract($contract);
            $manager->persist($contract);

            if ($data['email'] === 'entrepreneur@demo.test') {
                $seller = new User();
                $seller->setEmail('agent@demo.test');
                $seller->setFirstName('Moussa');
                $seller->setLastName('Fall');
                $seller->setPhone('+221770000099');
                $seller->setRoles([User::ROLE_EMPLOYEE]);
                $seller->setPassword($this->hasher->hashPassword($seller, bin2hex(random_bytes(16))));
                $manager->persist($seller);
                $manager->flush();

                $seller->setPreferredShop($shop);
                $member = new ShopMember();
                $member->setShop($shop);
                $member->setUser($seller);
                $member->setRole(ShopMember::ROLE_CASHIER);
                $member->setIsActive(true);
                $manager->persist($member);
            }

            $categories = [];
            foreach ($shopData['categories'] as $i => $categoryName) {
                $category = (new Category())
                    ->setShop($shop)
                    ->setName($categoryName)
                    ->setSortOrder($i + 1);
                $manager->persist($category);
                $categories[$categoryName] = $category;
            }

            $products = [];
            foreach ($shopData['products'] as $p) {
                [$name, $ref, $barcode, $brand, $catName, $buy, $sell, $qty, $min] = $p;
                $product = new Product();
                $product->setShop($shop);
                $product->setCategory($categories[$catName]);
                $product->setName($name);
                $product->setReference($ref);
                $product->setBarcode($barcode);
                $product->setBrand($brand);
                $product->setPurchasePrice(number_format((float) $buy, 2, '.', ''));
                $product->setSalePrice(number_format((float) $sell, 2, '.', ''));
                $product->setQuantity($qty);
                $product->setMinStock($min);
                $product->setIsActive(true);
                $this->photoGenerator->applyPlaceholder($product);
                $manager->persist($product);
                $products[] = $product;
            }

            foreach ($shopData['suppliers'] as [$sName, $sPhone, $sEmail, $sAddress]) {
                $manager->persist(
                    (new Supplier())->setShop($shop)->setName($sName)->setPhone($sPhone)->setEmail($sEmail)->setAddress($sAddress)
                );
            }

            $customers = [];
            foreach ($shopData['customers'] as [$cFirst, $cLast, $cPhone, $cEmail, $cAddress]) {
                $customer = (new Customer())
                    ->setShop($shop)
                    ->setFirstName($cFirst)
                    ->setLastName($cLast)
                    ->setPhone($cPhone)
                    ->setEmail($cEmail)
                    ->setAddress($cAddress);
                $manager->persist($customer);
                $customers[] = $customer;
            }

            // Ventes de démo réparties sur 7 jours
            $this->createDemoSales($manager, $shop, $user, $products, $customers, $paymentMethods);
        }

        $manager->flush();
    }

    /**
     * @param Product[] $products
     * @param Customer[] $customers
     * @param list<string> $paymentMethods
     */
    private function createDemoSales(
        ObjectManager $manager,
        Shop $shop,
        User $seller,
        array $products,
        array $customers,
        array $paymentMethods,
    ): void {
        if ($products === []) {
            return;
        }

        $saleCount = 10;
        for ($i = 0; $i < $saleCount; ++$i) {
            $daysAgo = $i % 7;
            $soldAt = (new \DateTimeImmutable('today'))
                ->modify(sprintf('-%d days', $daysAgo))
                ->setTime(8 + ($i % 10), ($i * 7) % 60);

            $sale = new Sale();
            $sale->setShop($shop);
            $sale->setSoldBy($seller);
            $sale->setSoldAt($soldAt);
            $sale->setReference('VTE-'.$soldAt->format('Ymd').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT));
            $sale->setStatus(Sale::STATUS_COMPLETED);
            $sale->setPaymentMethod($paymentMethods[$i % count($paymentMethods)]);

            $customer = null;
            if ($customers !== [] && $i % 3 !== 0) {
                $customer = $customers[$i % count($customers)];
                $sale->setCustomer($customer);
            }

            $lineCount = 1 + ($i % 3);
            $usedIndexes = [];
            for ($l = 0; $l < $lineCount; ++$l) {
                $index = ($i + $l * 3) % count($products);
                if (isset($usedIndexes[$index])) {
                    $index = ($index + 1) % count($products);
                }
                $usedIndexes[$index] = true;
                $product = $products[$index];
                $qty = 1 + ($i + $l) % 3;

                if ($product->getQuantity() < $qty) {
                    continue;
                }

                $before = $product->getQuantity();
                $product->setQuantity($before - $qty);

                $item = new SaleItem();
                $item->setProduct($product);
                $item->setQuantity($qty);
                $item->setUnitPrice($product->getSalePrice());
                $sale->addItem($item);

                $movement = new StockMovement();
                $movement->setShop($shop);
                $movement->setProduct($product);
                $movement->setType(StockMovement::TYPE_SALE);
                $movement->setQuantity(-$qty);
                $movement->setQuantityBefore($before);
                $movement->setQuantityAfter($product->getQuantity());
                $movement->setReason('Vente démo '.$sale->getReference());
                $movement->setCreatedBy($seller);
                $manager->persist($movement);
            }

            if ($sale->getItems()->isEmpty()) {
                continue;
            }

            $discount = ($i % 4 === 0) ? 500 : 0;
            $sale->setDiscount(number_format($discount, 2, '.', ''));
            $sale->recalculateTotals();

            $total = (float) $sale->getTotal();
            if ($sale->getPaymentMethod() === Sale::PAYMENT_CREDIT && $customer) {
                $paid = round($total * 0.5);
                $sale->setAmountPaid(number_format($paid, 2, '.', ''));
                $due = $total - $paid;
                $customer->setBalance(number_format((float) $customer->getBalance() + $due, 2, '.', ''));
            } else {
                $sale->setAmountPaid($sale->getTotal());
            }

            $invoice = new Invoice();
            $invoice->setSale($sale);
            $invoice->setType(Invoice::TYPE_INVOICE);
            $invoice->setNumber('FAC-'.$soldAt->format('Ymd').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT));
            $sale->setInvoice($invoice);

            $manager->persist($sale);
        }
    }
}
