<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813083919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE demo_people');
        $this->addSql('ALTER TABLE app_user CHANGE roles roles JSON NOT NULL, CHANGE datatable_columns datatable_columns JSON DEFAULT NULL, CHANGE grafana_dashboards grafana_dashboards JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE report CHANGE repsql repsql LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demo_people (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(50) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, last_name VARCHAR(50) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, city VARCHAR(80) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) DEFAULT CHARACTER SET latin1 COLLATE `latin1_swedish_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE app_user CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE datatable_columns datatable_columns JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE grafana_dashboards grafana_dashboards JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE available_at available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE report CHANGE repsql repsql LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
