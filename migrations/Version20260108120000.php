<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce gallery entity and link existing photos to a default gallery.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE gallery (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            share_token VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX uniq_gallery_slug (slug),
            UNIQUE INDEX uniq_gallery_share (share_token),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE gallery_photo ADD gallery_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_F02A543B4E7AF8F ON gallery_photo (gallery_id)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $token = $this->generateShareToken();

        $this->addSql(
            'INSERT INTO gallery (name, slug, share_token, created_at, updated_at) VALUES (:name, :slug, :token, :created, :updated)',
            [
                'name' => 'Default gallery',
                'slug' => 'default',
                'token' => $token,
                'created' => $now,
                'updated' => $now,
            ]
        );

        $this->addSql(
            'UPDATE gallery_photo SET gallery_id = (SELECT id FROM gallery WHERE slug = :slug LIMIT 1) WHERE gallery_id IS NULL',
            [
                'slug' => 'default',
            ]
        );

        $this->addSql('ALTER TABLE gallery_photo MODIFY gallery_id INT NOT NULL');
        $this->addSql('ALTER TABLE gallery_photo ADD CONSTRAINT FK_F02A543B4E7AF8F FOREIGN KEY (gallery_id) REFERENCES gallery (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gallery_photo DROP FOREIGN KEY FK_F02A543B4E7AF8F');
        $this->addSql('DROP INDEX IDX_F02A543B4E7AF8F ON gallery_photo');
        $this->addSql('ALTER TABLE gallery_photo DROP gallery_id');

        $this->addSql('DROP TABLE gallery');
    }

    private function generateShareToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
    }
}
