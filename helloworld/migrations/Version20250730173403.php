<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730173403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE report');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE report (rid INT AUTO_INCREMENT NOT NULL, rtitle VARCHAR(255) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, rshort VARCHAR(50) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, rsql TEXT CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, rparameter TEXT CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, rtype VARCHAR(255) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, rowner VARCHAR(255) CHARACTER SET latin1 DEFAULT NULL COLLATE `latin1_swedish_ci`, rts DATETIME DEFAULT NULL, rlastmodified DATETIME DEFAULT NULL, PRIMARY KEY(rid)) DEFAULT CHARACTER SET latin1 COLLATE `latin1_swedish_ci` ENGINE = InnoDB COMMENT = \'\' ');
    }
}
