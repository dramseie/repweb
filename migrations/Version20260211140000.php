<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for smartsheet task dependency modeler.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE smartsheet_task_dependency (
                id BIGINT AUTO_INCREMENT NOT NULL,
                name VARCHAR(190) NOT NULL,
                notes TEXT DEFAULT NULL,
                created_by VARCHAR(190) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE smartsheet_task_dependency_node (
                id BIGINT AUTO_INCREMENT NOT NULL,
                workspace_id BIGINT NOT NULL,
                task_name VARCHAR(255) NOT NULL,
                pos_x DOUBLE NOT NULL DEFAULT 0,
                pos_y DOUBLE NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_task_dep_node (workspace_id, task_name),
                INDEX idx_task_dep_node_workspace (workspace_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );

        $this->addSql(
            'CREATE TABLE smartsheet_task_dependency_edge (
                id BIGINT AUTO_INCREMENT NOT NULL,
                workspace_id BIGINT NOT NULL,
                source_node_id BIGINT NOT NULL,
                target_node_id BIGINT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_task_dep_edge (workspace_id, source_node_id, target_node_id),
                INDEX idx_task_dep_edge_workspace (workspace_id),
                INDEX idx_task_dep_edge_source (source_node_id),
                INDEX idx_task_dep_edge_target (target_node_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS smartsheet_task_dependency_edge');
        $this->addSql('DROP TABLE IF EXISTS smartsheet_task_dependency_node');
        $this->addSql('DROP TABLE IF EXISTS smartsheet_task_dependency');
    }
}
