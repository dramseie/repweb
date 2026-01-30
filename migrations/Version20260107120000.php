<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create gallery_photo table for NAS-backed photo gallery';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gallery_photo (
            id INT AUTO_INCREMENT NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(255) NOT NULL,
            size BIGINT NOT NULL,
            relative_path VARCHAR(255) NOT NULL,
            layout JSON DEFAULT NULL,
            display_order INT NOT NULL,
            uploaded_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            title VARCHAR(255) DEFAULT NULL,
            caption LONGTEXT DEFAULT NULL,
            width INT DEFAULT NULL,
            height INT DEFAULT NULL,
            UNIQUE INDEX uniq_gallery_path_file (relative_path, stored_filename),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE gallery_photo');
    }
}
