<?php
// NOTE: You said the tables already exist and you don't want Doctrine.
// This noop migration file exists ONLY to satisfy a file path some tooling might expect.
// It performs no schema changes.

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251010QW extends AbstractMigration
{
    public function getDescription(): string { return 'NOOP migration — schema already created manually.'; }
    public function up(Schema $schema): void { /* no-op */ }
    public function down(Schema $schema): void { /* no-op */ }
}
