<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name to smartsheet_presentation_snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE nifi.smartsheet_presentation_snapshot ADD name_snapshot VARCHAR(255) DEFAULT NULL AFTER id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE nifi.smartsheet_presentation_snapshot DROP COLUMN name_snapshot");
    }
}
