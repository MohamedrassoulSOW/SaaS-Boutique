<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coût unitaire figé sur les lignes de vente (bénéfices)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_items ADD unit_cost NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('UPDATE sale_items si
            INNER JOIN products p ON p.id = si.product_id
            SET si.unit_cost = p.purchase_price
            WHERE si.unit_cost IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_items DROP unit_cost');
    }
}
