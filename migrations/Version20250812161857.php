<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812161857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report ADD id INT AUTO_INCREMENT NOT NULL, CHANGE repid repid INT NOT NULL, CHANGE repdesc repdesc LONGTEXT DEFAULT NULL, CHANGE repsql repsql LONGTEXT NOT NULL, CHANGE repparam repparam LONGTEXT DEFAULT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report MODIFY id INT NOT NULL');
        $this->addSql('DROP INDEX `PRIMARY` ON report');
        $this->addSql('ALTER TABLE report DROP id, CHANGE repid repid INT AUTO_INCREMENT NOT NULL, CHANGE repdesc repdesc TEXT DEFAULT NULL, CHANGE repsql repsql TEXT DEFAULT NULL, CHANGE repparam repparam TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD PRIMARY KEY (repid)');
    }
}
