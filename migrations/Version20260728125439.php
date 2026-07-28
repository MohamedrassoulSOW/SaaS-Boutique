<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les clés étrangères absentes (schéma créé sans FK).
 */
final class Version20260728125439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des contraintes FOREIGN KEY manquantes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_logs ADD CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE activity_logs ADD CONSTRAINT FK_F34B1DCE4D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF346684D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE customers ADD CONSTRAINT FK_62534E214D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE inventories ADD CONSTRAINT FK_936C863D4D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE inventories ADD CONSTRAINT FK_936C863DB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE inventory_items ADD CONSTRAINT FK_3D82424D9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventories (id)');
        $this->addSql('ALTER TABLE inventory_items ADD CONSTRAINT FK_3D82424D4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F954A7E4868 FOREIGN KEY (sale_id) REFERENCES sales (id)');
        $this->addSql('ALTER TABLE merchants ADD CONSTRAINT FK_CC77B6C0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D34D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B329A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A4D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT FK_193D8549A45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id)');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT FK_193D85494584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_3E40FFBB4D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_3E40FFBB2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES suppliers (id)');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_3E40FFBBB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE sale_items ADD CONSTRAINT FK_31C2B1CE4A7E4868 FOREIGN KEY (sale_id) REFERENCES sales (id)');
        $this->addSql('ALTER TABLE sale_items ADD CONSTRAINT FK_31C2B1CE4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE sales ADD CONSTRAINT FK_6B8170444D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE sales ADD CONSTRAINT FK_6B8170449395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id)');
        $this->addSql('ALTER TABLE sales ADD CONSTRAINT FK_6B817044148EA8A1 FOREIGN KEY (sold_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE shop_contracts ADD CONSTRAINT FK_10E727F84D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE shop_contracts ADD CONSTRAINT FK_10E727F86796D554 FOREIGN KEY (merchant_id) REFERENCES merchants (id)');
        $this->addSql('ALTER TABLE shop_contracts ADD CONSTRAINT FK_10E727F8B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE shop_members ADD CONSTRAINT FK_469392D54D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE shop_members ADD CONSTRAINT FK_469392D5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE shops ADD CONSTRAINT FK_237A67836796D554 FOREIGN KEY (merchant_id) REFERENCES merchants (id)');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C94D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C94584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C9B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A016796D554 FOREIGN KEY (merchant_id) REFERENCES merchants (id)');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_AC28B95C4D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_logs DROP FOREIGN KEY FK_F34B1DCEA76ED395');
        $this->addSql('ALTER TABLE activity_logs DROP FOREIGN KEY FK_F34B1DCE4D16C4DD');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF346684D16C4DD');
        $this->addSql('ALTER TABLE customers DROP FOREIGN KEY FK_62534E214D16C4DD');
        $this->addSql('ALTER TABLE inventories DROP FOREIGN KEY FK_936C863D4D16C4DD');
        $this->addSql('ALTER TABLE inventories DROP FOREIGN KEY FK_936C863DB03A8386');
        $this->addSql('ALTER TABLE inventory_items DROP FOREIGN KEY FK_3D82424D9EEA759');
        $this->addSql('ALTER TABLE inventory_items DROP FOREIGN KEY FK_3D82424D4584665A');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_6A2F2F954A7E4868');
        $this->addSql('ALTER TABLE merchants DROP FOREIGN KEY FK_CC77B6C0A76ED395');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D34D16C4DD');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B329A1887DC');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5A4D16C4DD');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5A12469DE2');
        $this->addSql('ALTER TABLE purchase_order_items DROP FOREIGN KEY FK_193D8549A45D7E6A');
        $this->addSql('ALTER TABLE purchase_order_items DROP FOREIGN KEY FK_193D85494584665A');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_3E40FFBB4D16C4DD');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_3E40FFBB2ADD6D8C');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_3E40FFBBB03A8386');
        $this->addSql('ALTER TABLE sale_items DROP FOREIGN KEY FK_31C2B1CE4A7E4868');
        $this->addSql('ALTER TABLE sale_items DROP FOREIGN KEY FK_31C2B1CE4584665A');
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY FK_6B8170444D16C4DD');
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY FK_6B8170449395C3F3');
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY FK_6B817044148EA8A1');
        $this->addSql('ALTER TABLE shop_contracts DROP FOREIGN KEY FK_10E727F84D16C4DD');
        $this->addSql('ALTER TABLE shop_contracts DROP FOREIGN KEY FK_10E727F86796D554');
        $this->addSql('ALTER TABLE shop_contracts DROP FOREIGN KEY FK_10E727F8B03A8386');
        $this->addSql('ALTER TABLE shop_members DROP FOREIGN KEY FK_469392D54D16C4DD');
        $this->addSql('ALTER TABLE shop_members DROP FOREIGN KEY FK_469392D5A76ED395');
        $this->addSql('ALTER TABLE shops DROP FOREIGN KEY FK_237A67836796D554');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C94D16C4DD');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C94584665A');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C9B03A8386');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY FK_4778A016796D554');
        $this->addSql('ALTER TABLE suppliers DROP FOREIGN KEY FK_AC28B95C4D16C4DD');
    }
}
