<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiements clients, dépenses, réception partielle achats, séquence factures boutique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_payments (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT NOT NULL,
            shop_id INT NOT NULL,
            recorded_by_id INT DEFAULT NULL,
            amount NUMERIC(12, 2) NOT NULL,
            method VARCHAR(30) NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CP_CUSTOMER (customer_id),
            INDEX IDX_CP_SHOP (shop_id),
            INDEX IDX_CP_RECORDED_BY (recorded_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_payments ADD CONSTRAINT FK_CP_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customers (id)');
        $this->addSql('ALTER TABLE customer_payments ADD CONSTRAINT FK_CP_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE customer_payments ADD CONSTRAINT FK_CP_RECORDED_BY FOREIGN KEY (recorded_by_id) REFERENCES users (id)');

        $this->addSql('CREATE TABLE expenses (
            id INT AUTO_INCREMENT NOT NULL,
            shop_id INT NOT NULL,
            recorded_by_id INT DEFAULT NULL,
            category VARCHAR(60) NOT NULL,
            label VARCHAR(180) NOT NULL,
            amount NUMERIC(12, 2) NOT NULL,
            spent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_EXP_SHOP (shop_id),
            INDEX IDX_EXP_RECORDED_BY (recorded_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_EXP_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_EXP_RECORDED_BY FOREIGN KEY (recorded_by_id) REFERENCES users (id)');

        $this->addSql('ALTER TABLE purchase_order_items ADD received_quantity INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE shops ADD invoice_sequence INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_payments DROP FOREIGN KEY FK_CP_CUSTOMER');
        $this->addSql('ALTER TABLE customer_payments DROP FOREIGN KEY FK_CP_SHOP');
        $this->addSql('ALTER TABLE customer_payments DROP FOREIGN KEY FK_CP_RECORDED_BY');
        $this->addSql('DROP TABLE customer_payments');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_EXP_SHOP');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_EXP_RECORDED_BY');
        $this->addSql('DROP TABLE expenses');
        $this->addSql('ALTER TABLE purchase_order_items DROP received_quantity');
        $this->addSql('ALTER TABLE shops DROP invoice_sequence');
    }
}
