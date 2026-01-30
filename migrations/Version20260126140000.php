<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260126140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove orphaned migration version records for 20250730173403 and 20250730174106.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM doctrine_migration_versions WHERE version IN ('DoctrineMigrations\\Version20250730173403', 'DoctrineMigrations\\Version20250730174106')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("INSERT INTO doctrine_migration_versions (version, executed_at, execution_time)
            VALUES ('DoctrineMigrations\\Version20250730173403', NOW(), NULL)");
        $this->addSql("INSERT INTO doctrine_migration_versions (version, executed_at, execution_time)
            VALUES ('DoctrineMigrations\\Version20250730174106', NOW(), NULL)");
    }
}
