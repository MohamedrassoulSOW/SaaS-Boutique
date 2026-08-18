<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817233210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optimistic locking version column to invoices, payments, cash_sessions, customer_payments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE payments ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE cash_sessions ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE customer_payments ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices DROP version');
        $this->addSql('ALTER TABLE payments DROP version');
        $this->addSql('ALTER TABLE cash_sessions DROP version');
        $this->addSql('ALTER TABLE customer_payments DROP version');
    }
}
