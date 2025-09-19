<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

final class Version20250909xxxxxx extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change dt_saved_filter.owner_id from BIGINT to VARCHAR(191) and reindex.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!($platform instanceof AbstractMySQLPlatform)) {
            $this->abortIf(true, "Migration can only be executed safely on MySQL/MariaDB.");
        }

        // Change the column type
        $this->addSql("ALTER TABLE dt_saved_filter MODIFY owner_id VARCHAR(191) NULL");

        // Drop index if it exists (name might already exist)
        $exists = (int)$this->connection->fetchOne("
            SELECT COUNT(1)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dt_saved_filter'
              AND INDEX_NAME = 'idx_table_owner'
        ");
        if ($exists > 0) {
            $this->addSql("ALTER TABLE dt_saved_filter DROP INDEX idx_table_owner");
        }

        // Recreate with the new column definition
        $this->addSql("ALTER TABLE dt_saved_filter ADD INDEX idx_table_owner (table_key, owner_id)");
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!($platform instanceof AbstractMySQLPlatform)) {
            $this->abortIf(true, "Migration can only be executed safely on MySQL/MariaDB.");
        }

        // Drop index if exists
        $exists = (int)$this->connection->fetchOne("
            SELECT COUNT(1)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dt_saved_filter'
              AND INDEX_NAME = 'idx_table_owner'
        ");
        if ($exists > 0) {
            $this->addSql("ALTER TABLE dt_saved_filter DROP INDEX idx_table_owner");
        }

        // Revert column type (unsigned BIGINT to match your original)
        $this->addSql("ALTER TABLE dt_saved_filter MODIFY owner_id BIGINT UNSIGNED NULL");

        // Recreate index
        $this->addSql("ALTER TABLE dt_saved_filter ADD INDEX idx_table_owner (table_key, owner_id)");
    }
}
