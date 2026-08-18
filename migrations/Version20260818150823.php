<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260818150823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories RENAME INDEX idx_3af346684d16c4dd TO IDX_CATEGORY_SHOP');
        $this->addSql('ALTER TABLE customer_payments CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE customer_payments RENAME INDEX idx_cp_customer TO IDX_CUSTPAY_CUSTOMER');
        $this->addSql('ALTER TABLE customer_payments RENAME INDEX idx_cp_shop TO IDX_CUSTPAY_SHOP');
        $this->addSql('ALTER TABLE customer_payments RENAME INDEX idx_cp_recorded_by TO IDX_CUSTPAY_RECORDED_BY');
        $this->addSql('DROP INDEX IDX_CUSTOMERS_SHOP_BALANCE ON customers');
        $this->addSql('ALTER TABLE customers RENAME INDEX idx_62534e214d16c4dd TO IDX_CUSTOMER_SHOP');
        $this->addSql('DROP INDEX IDX_EXPENSES_SHOP_SPENT ON expenses');
        $this->addSql('ALTER TABLE expenses CHANGE spent_at spent_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE expenses RENAME INDEX idx_exp_shop TO IDX_2496F35B4D16C4DD');
        $this->addSql('ALTER TABLE expenses RENAME INDEX idx_exp_recorded_by TO IDX_2496F35BD05A957B');
        $this->addSql('ALTER TABLE inventories RENAME INDEX idx_936c863d4d16c4dd TO IDX_INVENTORY_SHOP');
        $this->addSql('ALTER TABLE inventories RENAME INDEX idx_936c863db03a8386 TO IDX_INVENTORY_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_items RENAME INDEX idx_3d82424d9eea759 TO IDX_INVITEM_INVENTORY');
        $this->addSql('ALTER TABLE inventory_items RENAME INDEX idx_3d82424d4584665a TO IDX_INVITEM_PRODUCT');
        $this->addSql('CREATE INDEX IDX_INVOICE_SALE ON invoices (sale_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_USER ON merchants (user_id)');
        $this->addSql('ALTER TABLE payments RENAME INDEX idx_65d29b329a1887dc TO IDX_PAYMENT_SUBSCRIPTION');
        $this->addSql('ALTER TABLE products RENAME INDEX idx_b3ba5a5a4d16c4dd TO IDX_PRODUCT_SHOP');
        $this->addSql('ALTER TABLE products RENAME INDEX idx_b3ba5a5a12469de2 TO IDX_PRODUCT_CATEGORY');
        $this->addSql('ALTER TABLE purchase_order_items RENAME INDEX idx_193d8549a45d7e6a TO IDX_POI_ORDER');
        $this->addSql('ALTER TABLE purchase_order_items RENAME INDEX idx_193d85494584665a TO IDX_POI_PRODUCT');
        $this->addSql('ALTER TABLE purchase_orders RENAME INDEX idx_3e40ffbb4d16c4dd TO IDX_PO_SHOP');
        $this->addSql('ALTER TABLE purchase_orders RENAME INDEX idx_3e40ffbb2add6d8c TO IDX_PO_SUPPLIER');
        $this->addSql('ALTER TABLE purchase_orders RENAME INDEX idx_3e40ffbbb03a8386 TO IDX_PO_CREATED_BY');
        $this->addSql('ALTER TABLE sale_items RENAME INDEX idx_31c2b1ce4a7e4868 TO IDX_SALEITEM_SALE');
        $this->addSql('ALTER TABLE sale_items RENAME INDEX idx_31c2b1ce4584665a TO IDX_SALEITEM_PRODUCT');
        $this->addSql('DROP INDEX IDX_SALES_SHOP_STATUS_SOLD ON sales');
        $this->addSql('CREATE INDEX IDX_SC_SHOP ON shop_contracts (shop_id)');
        $this->addSql('ALTER TABLE shop_contracts RENAME INDEX idx_10e727f86796d554 TO IDX_SC_MERCHANT');
        $this->addSql('ALTER TABLE shop_contracts RENAME INDEX idx_10e727f8b03a8386 TO IDX_SC_CREATED_BY');
        $this->addSql('ALTER TABLE shops ADD currency VARCHAR(5) DEFAULT \'XOF\' NOT NULL');
        $this->addSql('ALTER TABLE shops RENAME INDEX idx_237a67836796d554 TO IDX_SHOP_MERCHANT');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_MERCHANT ON subscriptions (merchant_id)');
        $this->addSql('ALTER TABLE suppliers RENAME INDEX idx_ac28b95c4d16c4dd TO IDX_SUPPLIER_SHOP');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9E7E5EE02 FOREIGN KEY (preferred_shop_id) REFERENCES shops (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_USER_PREFERRED_SHOP ON users (preferred_shop_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories RENAME INDEX idx_category_shop TO IDX_3AF346684D16C4DD');
        $this->addSql('ALTER TABLE customer_payments CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE customer_payments RENAME INDEX idx_custpay_customer TO IDX_CP_CUSTOMER');
        $this->addSql('ALTER TABLE customer_payments RENAME INDEX idx_custpay_recorded_by TO IDX_CP_RECORDED_BY');
        $this->addSql('ALTER TABLE customer_payments RENAME INDEX idx_custpay_shop TO IDX_CP_SHOP');
        $this->addSql('CREATE INDEX IDX_CUSTOMERS_SHOP_BALANCE ON customers (shop_id, balance)');
        $this->addSql('ALTER TABLE customers RENAME INDEX idx_customer_shop TO IDX_62534E214D16C4DD');
        $this->addSql('ALTER TABLE expenses CHANGE spent_at spent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_EXPENSES_SHOP_SPENT ON expenses (shop_id, spent_at)');
        $this->addSql('ALTER TABLE expenses RENAME INDEX idx_2496f35bd05a957b TO IDX_EXP_RECORDED_BY');
        $this->addSql('ALTER TABLE expenses RENAME INDEX idx_2496f35b4d16c4dd TO IDX_EXP_SHOP');
        $this->addSql('ALTER TABLE inventories RENAME INDEX idx_inventory_shop TO IDX_936C863D4D16C4DD');
        $this->addSql('ALTER TABLE inventories RENAME INDEX idx_inventory_created_by TO IDX_936C863DB03A8386');
        $this->addSql('ALTER TABLE inventory_items RENAME INDEX idx_invitem_product TO IDX_3D82424D4584665A');
        $this->addSql('ALTER TABLE inventory_items RENAME INDEX idx_invitem_inventory TO IDX_3D82424D9EEA759');
        $this->addSql('DROP INDEX IDX_INVOICE_SALE ON invoices');
        $this->addSql('DROP INDEX IDX_MERCHANT_USER ON merchants');
        $this->addSql('ALTER TABLE payments RENAME INDEX idx_payment_subscription TO IDX_65D29B329A1887DC');
        $this->addSql('ALTER TABLE products RENAME INDEX idx_product_category TO IDX_B3BA5A5A12469DE2');
        $this->addSql('ALTER TABLE products RENAME INDEX idx_product_shop TO IDX_B3BA5A5A4D16C4DD');
        $this->addSql('ALTER TABLE purchase_order_items RENAME INDEX idx_poi_product TO IDX_193D85494584665A');
        $this->addSql('ALTER TABLE purchase_order_items RENAME INDEX idx_poi_order TO IDX_193D8549A45D7E6A');
        $this->addSql('ALTER TABLE purchase_orders RENAME INDEX idx_po_supplier TO IDX_3E40FFBB2ADD6D8C');
        $this->addSql('ALTER TABLE purchase_orders RENAME INDEX idx_po_shop TO IDX_3E40FFBB4D16C4DD');
        $this->addSql('ALTER TABLE purchase_orders RENAME INDEX idx_po_created_by TO IDX_3E40FFBBB03A8386');
        $this->addSql('ALTER TABLE sale_items RENAME INDEX idx_saleitem_product TO IDX_31C2B1CE4584665A');
        $this->addSql('ALTER TABLE sale_items RENAME INDEX idx_saleitem_sale TO IDX_31C2B1CE4A7E4868');
        $this->addSql('CREATE INDEX IDX_SALES_SHOP_STATUS_SOLD ON sales (shop_id, status, sold_at)');
        $this->addSql('DROP INDEX IDX_SC_SHOP ON shop_contracts');
        $this->addSql('ALTER TABLE shop_contracts RENAME INDEX idx_sc_merchant TO IDX_10E727F86796D554');
        $this->addSql('ALTER TABLE shop_contracts RENAME INDEX idx_sc_created_by TO IDX_10E727F8B03A8386');
        $this->addSql('ALTER TABLE shops DROP currency');
        $this->addSql('ALTER TABLE shops RENAME INDEX idx_shop_merchant TO IDX_237A67836796D554');
        $this->addSql('DROP INDEX IDX_SUBSCRIPTION_MERCHANT ON subscriptions');
        $this->addSql('ALTER TABLE suppliers RENAME INDEX idx_supplier_shop TO IDX_AC28B95C4D16C4DD');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9E7E5EE02');
        $this->addSql('DROP INDEX IDX_USER_PREFERRED_SHOP ON users');
    }
}
