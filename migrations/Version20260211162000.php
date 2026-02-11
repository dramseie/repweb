<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task calculator run/results tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE smartsheet_task_calc_run (
                id BIGINT AUTO_INCREMENT NOT NULL,
                workspace_id BIGINT NOT NULL,
                country VARCHAR(190) DEFAULT NULL,
                site_name VARCHAR(255) DEFAULT NULL,
                dependency_type VARCHAR(50) NOT NULL DEFAULT \'finish-to-start\',
                created_by VARCHAR(190) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE smartsheet_task_calc_result (
                id BIGINT AUTO_INCREMENT NOT NULL,
                run_id BIGINT NOT NULL,
                workspace_id BIGINT NOT NULL,
                country VARCHAR(190) NOT NULL,
                site_name VARCHAR(255) NOT NULL,
                task_name VARCHAR(255) NOT NULL,
                current_start DATE DEFAULT NULL,
                current_end DATE DEFAULT NULL,
                proposed_start DATE DEFAULT NULL,
                proposed_end DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_task_calc_result_run (run_id),
                INDEX idx_task_calc_result_workspace (workspace_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS smartsheet_task_calc_result');
        $this->addSql('DROP TABLE IF EXISTS smartsheet_task_calc_run');
    }
}
