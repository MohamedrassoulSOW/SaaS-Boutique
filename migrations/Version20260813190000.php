<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indexes perf prod : ventes, dépenses, clients endettés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_SALES_SHOP_STATUS_SOLD ON sales (shop_id, status, sold_at)');
        $this->addSql('CREATE INDEX IDX_EXPENSES_SHOP_SPENT ON expenses (shop_id, spent_at)');
        $this->addSql('CREATE INDEX IDX_CUSTOMERS_SHOP_BALANCE ON customers (shop_id, balance)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_SALES_SHOP_STATUS_SOLD ON sales');
        $this->addSql('DROP INDEX IDX_EXPENSES_SHOP_SPENT ON expenses');
        $this->addSql('DROP INDEX IDX_CUSTOMERS_SHOP_BALANCE ON customers');
    }
}
