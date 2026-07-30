<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sessions de caisse + onboarding utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cash_sessions (
            id INT AUTO_INCREMENT NOT NULL,
            shop_id INT NOT NULL,
            opened_by_id INT DEFAULT NULL,
            closed_by_id INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            opening_float NUMERIC(12, 2) NOT NULL,
            closing_counted NUMERIC(12, 2) DEFAULT NULL,
            expected_cash NUMERIC(12, 2) DEFAULT NULL,
            difference NUMERIC(12, 2) DEFAULT NULL,
            notes VARCHAR(500) DEFAULT NULL,
            opened_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CASH_SHOP (shop_id),
            INDEX IDX_CASH_OPENED_BY (opened_by_id),
            INDEX IDX_CASH_CLOSED_BY (closed_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cash_sessions ADD CONSTRAINT FK_CASH_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id)');
        $this->addSql('ALTER TABLE cash_sessions ADD CONSTRAINT FK_CASH_OPENED_BY FOREIGN KEY (opened_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE cash_sessions ADD CONSTRAINT FK_CASH_CLOSED_BY FOREIGN KEY (closed_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users ADD onboarding_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_sessions DROP FOREIGN KEY FK_CASH_SHOP');
        $this->addSql('ALTER TABLE cash_sessions DROP FOREIGN KEY FK_CASH_OPENED_BY');
        $this->addSql('ALTER TABLE cash_sessions DROP FOREIGN KEY FK_CASH_CLOSED_BY');
        $this->addSql('DROP TABLE cash_sessions');
        $this->addSql('ALTER TABLE users DROP onboarding_completed_at');
    }
}
