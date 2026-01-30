<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260126143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOTP fields to user for two-factor authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD totp_secret VARCHAR(128) DEFAULT NULL, ADD totp_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP totp_secret, DROP totp_enabled');
    }
}
