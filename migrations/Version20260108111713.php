<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108111713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deprecated placeholder migration (no-op).';
    }

    public function up(Schema $schema): void
    {
        // No-op: superseded by Version20260108120000.
    }

    public function down(Schema $schema): void
    {
        // No-op: superseded by Version20260108120000.
    }
}
