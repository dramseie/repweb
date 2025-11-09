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
        $this->addSql("ALTER TABLE ongleri.customers ADD status ENUM('active','inactive','banned','test') NOT NULL DEFAULT 'active' AFTER gdpr_ok");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ongleri.customers DROP COLUMN status');
    }
}
