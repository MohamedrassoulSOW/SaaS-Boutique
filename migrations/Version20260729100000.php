<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fiscalité plateforme + boutiques + TVA sur ventes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_fiscal_settings (
            id INT NOT NULL,
            legal_name VARCHAR(150) NOT NULL,
            tax_id VARCHAR(80) DEFAULT NULL,
            registration_number VARCHAR(80) DEFAULT NULL,
            legal_form VARCHAR(80) DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            country VARCHAR(100) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            default_vat_rate NUMERIC(5, 2) NOT NULL,
            default_prices_include_tax TINYINT(1) NOT NULL,
            tax_on_subscriptions TINYINT(1) NOT NULL,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO platform_fiscal_settings (
            id, legal_name, tax_id, registration_number, legal_form, address, city, country, email, phone,
            default_vat_rate, default_prices_include_tax, tax_on_subscriptions, updated_at
        ) VALUES (
            1, 'NdamStore SARL', 'SN-NINEA-000000000', NULL, 'SARL',
            'Immeuble Horizon, Avenue Cheikh Anta Diop', 'Dakar', 'Sénégal',
            'contact@ndamstore.local', '+221 33 800 00 00',
            18.00, 1, 1, NOW()
        )");

        $this->addSql('ALTER TABLE shops ADD tax_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD vat_rate NUMERIC(5, 2) DEFAULT NULL, ADD prices_include_tax TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE sales ADD tax_rate NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, ADD tax_amount NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, ADD prices_include_tax TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE payments ADD tax_rate NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, ADD tax_amount NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments DROP tax_rate, DROP tax_amount');
        $this->addSql('ALTER TABLE sales DROP tax_rate, DROP tax_amount, DROP prices_include_tax');
        $this->addSql('ALTER TABLE shops DROP tax_enabled, DROP vat_rate, DROP prices_include_tax');
        $this->addSql('DROP TABLE platform_fiscal_settings');
    }
}
