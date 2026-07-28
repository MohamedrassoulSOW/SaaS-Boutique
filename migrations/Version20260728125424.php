<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline schéma BoutiqueSaaS (état courant au 2026-07-28).
 * Marquée exécutée via migrations:rollup sur les bases déjà synchronisées.
 */
final class Version20260728125424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Baseline schéma initial BoutiqueSaaS';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_logs (
              id INT AUTO_INCREMENT NOT NULL,
              action VARCHAR(80) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              description VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              ip_address VARCHAR(45) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              created_at DATETIME NOT NULL,
              user_id INT DEFAULT NULL,
              shop_id INT DEFAULT NULL,
              INDEX IDX_F34B1DCE4D16C4DD (shop_id),
              INDEX IDX_F34B1DCEA76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE categories (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(120) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              description VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              sort_order INT NOT NULL,
              shop_id INT NOT NULL,
              INDEX IDX_3AF346684D16C4DD (shop_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE customers (
              id INT AUTO_INCREMENT NOT NULL,
              first_name VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              last_name VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              phone VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              email VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              balance NUMERIC(12, 2) NOT NULL,
              created_at DATETIME NOT NULL,
              shop_id INT NOT NULL,
              INDEX IDX_62534E214D16C4DD (shop_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventories (
              id INT AUTO_INCREMENT NOT NULL,
              reference VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              type VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              created_at DATETIME NOT NULL,
              completed_at DATETIME DEFAULT NULL,
              shop_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              INDEX IDX_936C863D4D16C4DD (shop_id),
              INDEX IDX_936C863DB03A8386 (created_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_items (
              id INT AUTO_INCREMENT NOT NULL,
              theoretical_qty INT NOT NULL,
              real_qty INT NOT NULL,
              inventory_id INT NOT NULL,
              product_id INT NOT NULL,
              INDEX IDX_3D82424D4584665A (product_id),
              INDEX IDX_3D82424D9EEA759 (inventory_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE invoices (
              id INT AUTO_INCREMENT NOT NULL,
              number VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              type VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              issued_at DATETIME NOT NULL,
              pdf_data LONGBLOB DEFAULT NULL,
              pdf_mime VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              sale_id INT NOT NULL,
              UNIQUE INDEX UNIQ_6A2F2F954A7E4868 (sale_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE merchants (
              id INT AUTO_INCREMENT NOT NULL,
              company_name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              tax_id VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              city VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              country VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              created_at DATETIME NOT NULL,
              user_id INT NOT NULL,
              legal_form VARCHAR(80) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              registration_number VARCHAR(80) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              representative_title VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              postal_code VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              UNIQUE INDEX UNIQ_CC77B6C0A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              title VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              message VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              is_read TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              user_id INT NOT NULL,
              shop_id INT DEFAULT NULL,
              INDEX IDX_6000B0D34D16C4DD (shop_id),
              INDEX IDX_6000B0D3A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
              id INT AUTO_INCREMENT NOT NULL,
              amount NUMERIC(12, 2) NOT NULL,
              status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              method VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              reference VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              created_at DATETIME NOT NULL,
              paid_at DATETIME DEFAULT NULL,
              subscription_id INT NOT NULL,
              due_at DATETIME DEFAULT NULL,
              INDEX IDX_65D29B329A1887DC (subscription_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE products (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              reference VARCHAR(80) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              barcode VARCHAR(80) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              brand VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              purchase_price NUMERIC(12, 2) NOT NULL,
              sale_price NUMERIC(12, 2) NOT NULL,
              quantity INT NOT NULL,
              min_stock INT NOT NULL,
              description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              photo_data LONGBLOB DEFAULT NULL,
              photo_mime VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              photo_name VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              is_active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              shop_id INT NOT NULL,
              category_id INT DEFAULT NULL,
              INDEX IDX_B3BA5A5A12469DE2 (category_id),
              INDEX IDX_B3BA5A5A4D16C4DD (shop_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_order_items (
              id INT AUTO_INCREMENT NOT NULL,
              quantity INT NOT NULL,
              unit_price NUMERIC(12, 2) NOT NULL,
              purchase_order_id INT NOT NULL,
              product_id INT NOT NULL,
              INDEX IDX_193D85494584665A (product_id),
              INDEX IDX_193D8549A45D7E6A (purchase_order_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_orders (
              id INT AUTO_INCREMENT NOT NULL,
              reference VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              total NUMERIC(12, 2) NOT NULL,
              created_at DATETIME NOT NULL,
              received_at DATETIME DEFAULT NULL,
              shop_id INT NOT NULL,
              supplier_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              INDEX IDX_3E40FFBB2ADD6D8C (supplier_id),
              INDEX IDX_3E40FFBB4D16C4DD (shop_id),
              INDEX IDX_3E40FFBBB03A8386 (created_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE sale_items (
              id INT AUTO_INCREMENT NOT NULL,
              quantity INT NOT NULL,
              unit_price NUMERIC(12, 2) NOT NULL,
              sale_id INT NOT NULL,
              product_id INT NOT NULL,
              INDEX IDX_31C2B1CE4584665A (product_id),
              INDEX IDX_31C2B1CE4A7E4868 (sale_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE sales (
              id INT AUTO_INCREMENT NOT NULL,
              reference VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              subtotal NUMERIC(12, 2) NOT NULL,
              discount NUMERIC(12, 2) NOT NULL,
              total NUMERIC(12, 2) NOT NULL,
              amount_paid NUMERIC(12, 2) NOT NULL,
              payment_method VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              sold_at DATETIME NOT NULL,
              shop_id INT NOT NULL,
              customer_id INT DEFAULT NULL,
              sold_by_id INT DEFAULT NULL,
              INDEX IDX_6B817044148EA8A1 (sold_by_id),
              INDEX IDX_6B8170444D16C4DD (shop_id),
              INDEX IDX_6B8170449395C3F3 (customer_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
              sess_id VARBINARY(128) NOT NULL,
              sess_data LONGBLOB NOT NULL,
              sess_lifetime INT UNSIGNED NOT NULL,
              sess_time INT UNSIGNED NOT NULL,
              INDEX sess_lifetime_idx (sess_lifetime),
              PRIMARY KEY (sess_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_contracts (
              id INT AUTO_INCREMENT NOT NULL,
              number VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              plan VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              price NUMERIC(12, 2) NOT NULL,
              duration_months INT NOT NULL,
              starts_at DATETIME NOT NULL,
              ends_at DATETIME NOT NULL,
              terms_version VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              created_at DATETIME NOT NULL,
              platform_signed_by VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              platform_signed_at DATETIME DEFAULT NULL,
              merchant_signed_by VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              merchant_signed_title VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              merchant_signed_at DATETIME DEFAULT NULL,
              pdf_data LONGBLOB DEFAULT NULL,
              pdf_mime VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              shop_id INT DEFAULT NULL,
              merchant_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              billing_period VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              proposed_shop_name VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              proposed_shop_address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              proposed_shop_phone VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              proposed_shop_email VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              discussion_notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              shared_with_merchant TINYINT NOT NULL,
              INDEX IDX_10E727F86796D554 (merchant_id),
              INDEX IDX_10E727F8B03A8386 (created_by_id),
              UNIQUE INDEX UNIQ_10E727F84D16C4DD (shop_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_members (
              id INT AUTO_INCREMENT NOT NULL,
              role VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              permissions JSON NOT NULL,
              is_active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              shop_id INT NOT NULL,
              user_id INT NOT NULL,
              INDEX IDX_469392D54D16C4DD (shop_id),
              INDEX IDX_469392D5A76ED395 (user_id),
              UNIQUE INDEX UNIQ_SHOP_USER (shop_id, user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE shops (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              phone VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              email VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              logo_data LONGBLOB DEFAULT NULL,
              logo_mime VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              logo_name VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              is_active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              merchant_id INT NOT NULL,
              INDEX IDX_237A67836796D554 (merchant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE stock_movements (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              quantity INT NOT NULL,
              quantity_before INT NOT NULL,
              quantity_after INT NOT NULL,
              reason VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              created_at DATETIME NOT NULL,
              shop_id INT NOT NULL,
              product_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              INDEX IDX_A0BE93C94584665A (product_id),
              INDEX IDX_A0BE93C94D16C4DD (shop_id),
              INDEX IDX_A0BE93C9B03A8386 (created_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE subscriptions (
              id INT AUTO_INCREMENT NOT NULL,
              plan VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              price NUMERIC(12, 2) NOT NULL,
              starts_at DATETIME NOT NULL,
              ends_at DATETIME DEFAULT NULL,
              merchant_id INT NOT NULL,
              billing_period VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              next_due_at DATETIME DEFAULT NULL,
              last_paid_at DATETIME DEFAULT NULL,
              last_enforcement_action VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              last_enforcement_at DATETIME DEFAULT NULL,
              UNIQUE INDEX UNIQ_4778A016796D554 (merchant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE suppliers (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              phone VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              email VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              shop_id INT NOT NULL,
              INDEX IDX_AC28B95C4D16C4DD (shop_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              roles JSON NOT NULL,
              password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              first_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              last_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`,
              phone VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              is_active TINYINT NOT NULL,
              is_suspended TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              password_reset_requested_at DATETIME DEFAULT NULL,
              password_reset_token VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`,
              preferred_shop_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_USER_EMAIL (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = ''
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `activity_logs`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `categories`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `customers`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `inventories`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `inventory_items`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `invoices`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `merchants`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `notifications`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `payments`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `products`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `purchase_order_items`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `purchase_orders`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `sale_items`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `sales`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `sessions`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `shop_contracts`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `shop_members`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `shops`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `stock_movements`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `subscriptions`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `suppliers`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQL80Platform'."
        );

        $this->addSql('DROP TABLE `users`');
    }
}
