<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812082824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY fk_menu_parent');
        $this->addSql('DROP INDEX fk_menu_parent ON menu_item');
        $this->addSql('ALTER TABLE menu_item CHANGE route_params route_params LONGTEXT DEFAULT NULL, CHANGE position position INT DEFAULT 0 NOT NULL, CHANGE roles roles LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_item CHANGE route_params route_params JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE position position INT DEFAULT 0, CHANGE roles roles JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT fk_menu_parent FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_menu_parent ON menu_item (parent_id)');
    }
}
