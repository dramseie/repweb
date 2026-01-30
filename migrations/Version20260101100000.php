<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add main_kind column to accounting categories and seed default mappings.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('ongleri', 'accounting_categories')) {
            return;
        }

        if (!$this->columnExists('ongleri', 'accounting_categories', 'main_kind')) {
            $this->addSql("ALTER TABLE ongleri.accounting_categories ADD COLUMN main_kind ENUM('asset','liability','income','expense') NOT NULL DEFAULT 'asset'");

            // Default mapping: expenses -> charge, others -> asset before overrides.
            $this->addSql("UPDATE ongleri.accounting_categories SET main_kind = CASE WHEN kind = 'expense' THEN 'expense' ELSE 'asset' END");

            // Specific overrides based on existing naming conventions.
            $this->addSql("UPDATE ongleri.accounting_categories SET main_kind = 'asset' WHERE name IN ('Caisse Jaune', 'Coffre Fort', 'Crédit Mutuel Pro')");
            $this->addSql("UPDATE ongleri.accounting_categories SET main_kind = 'liability' WHERE name IN ('Crédit Mutuel Perso')");
            $this->addSql("UPDATE ongleri.accounting_categories SET main_kind = 'income' WHERE name IN ('Recette Client')");
            $this->addSql("UPDATE ongleri.accounting_categories SET main_kind = 'expense' WHERE name IN ('Formation', 'URSSAF', 'Fournitures', 'Abonnement')");
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('ongleri', 'accounting_categories')) {
            return;
        }

        if ($this->columnExists('ongleri', 'accounting_categories', 'main_kind')) {
            $this->addSql('ALTER TABLE ongleri.accounting_categories DROP COLUMN main_kind');
        }
    }

    private function tableExists(string $schema, string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table';
        $count = (int) $this->connection->fetchOne($sql, ['schema' => $schema, 'table' => $table]);

        return $count > 0;
    }

    private function columnExists(string $schema, string $table, string $column): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column';
        $count = (int) $this->connection->fetchOne($sql, ['schema' => $schema, 'table' => $table, 'column' => $column]);

        return $count > 0;
    }
}
