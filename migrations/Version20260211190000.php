<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore AUTO_INCREMENT on smartsheet_task_manager.id after widening.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_manager DROP FOREIGN KEY FK_5C6E4D6E727ACA70');
        $this->addSql('ALTER TABLE smartsheet_task_manager MODIFY id BIGINT NOT NULL AUTO_INCREMENT');
        $this->addSql('ALTER TABLE smartsheet_task_manager MODIFY parent_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE smartsheet_task_manager ADD CONSTRAINT FK_5C6E4D6E727ACA70 FOREIGN KEY (parent_id) REFERENCES smartsheet_task_manager (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_manager DROP FOREIGN KEY FK_5C6E4D6E727ACA70');
        $this->addSql('ALTER TABLE smartsheet_task_manager MODIFY id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE smartsheet_task_manager ADD CONSTRAINT FK_5C6E4D6E727ACA70 FOREIGN KEY (parent_id) REFERENCES smartsheet_task_manager (id) ON DELETE CASCADE');
    }
}
