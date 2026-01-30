<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gallery photo metadata fields for creator info and watermark options';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gallery_photo ADD creator VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gallery_photo ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE gallery_photo ADD watermark_options JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gallery_photo DROP creator');
        $this->addSql('ALTER TABLE gallery_photo DROP metadata');
        $this->addSql('ALTER TABLE gallery_photo DROP watermark_options');
    }
}
