<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250821083935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE demo_people');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D754D550727ACA70 ON menu_item (parent_id)');
        $this->addSql('ALTER TABLE report_tile CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE user_tile CHANGE position position INT NOT NULL, CHANGE pinned pinned TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE user_tile RENAME INDEX fk_user_tile_report_tile TO IDX_C0EE5CD638AF48B');
        $this->addSql('ALTER TABLE user_tile RENAME INDEX unq_user_tile TO UNIQ_C0EE5CDA76ED395638AF48B');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demo_people (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(50) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, last_name VARCHAR(50) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, city VARCHAR(80) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) DEFAULT CHARACTER SET latin1 COLLATE `latin1_swedish_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_D754D550727ACA70');
        $this->addSql('DROP INDEX IDX_D754D550727ACA70 ON menu_item');
        $this->addSql('ALTER TABLE report_tile CHANGE title title VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE user_tile CHANGE position position INT DEFAULT 0 NOT NULL, CHANGE pinned pinned TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user_tile RENAME INDEX idx_c0ee5cd638af48b TO FK_user_tile_report_tile');
        $this->addSql('ALTER TABLE user_tile RENAME INDEX uniq_c0ee5cda76ed395638af48b TO UNQ_user_tile');
    }
}
