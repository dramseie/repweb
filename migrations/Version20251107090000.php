<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251107090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer status enum to ongleri.customers';
    }

    public function up(Schema $schema): void
    {
        if ($this->columnExists('ongleri', 'customers', 'status')) {
            return;
        }

        $this->addSql("ALTER TABLE ongleri.customers ADD status ENUM('active','inactive','banned','test') NOT NULL DEFAULT 'active' AFTER gdpr_ok");
    }

    public function down(Schema $schema): void
    {
        if (!$this->columnExists('ongleri', 'customers', 'status')) {
            return;
        }

        $this->addSql('ALTER TABLE ongleri.customers DROP COLUMN status');
    }

    private function columnExists(string $schema, string $table, string $column): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column';
        $count = (int) $this->connection->fetchOne($sql, [
            'schema' => $schema,
            'table' => $table,
            'column' => $column,
        ]);

        return $count > 0;
    }
}
