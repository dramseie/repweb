<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260128090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add smartsheet_content table for presentation highlights.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE smartsheet_content (
            id INT AUTO_INCREMENT NOT NULL,
            section VARCHAR(100) NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_SMARTSHEET_CONTENT_SECTION (section),
            INDEX IDX_SMARTSHEET_CONTENT_CREATED_AT (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE smartsheet_content');
    }
}
