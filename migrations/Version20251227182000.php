<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251227182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core tables for Smartsheet integration and issue tracking system.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('smartsheet_projects')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE smartsheet_projects (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    smartsheet_sheet_id BIGINT NOT NULL,
                    project_name VARCHAR(255) NOT NULL,
                    project_code VARCHAR(50) NOT NULL,
                    description LONGTEXT DEFAULT NULL,
                    start_date DATE DEFAULT NULL,
                    end_date DATE DEFAULT NULL,
                    status VARCHAR(20) NOT NULL,
                    last_sync_at DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE INDEX UNIQ_D2125F4DAA090F5 (smartsheet_sheet_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }

        if (!$schema->hasTable('smartsheet_tasks')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE smartsheet_tasks (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    smartsheet_row_id BIGINT NOT NULL,
                    project_id BIGINT NOT NULL,
                    parent_task_id BIGINT DEFAULT NULL,
                    task_name VARCHAR(255) NOT NULL,
                    task_number VARCHAR(50) DEFAULT NULL,
                    description LONGTEXT DEFAULT NULL,
                    due_date DATE DEFAULT NULL,
                    start_date DATE DEFAULT NULL,
                    assigned_to VARCHAR(255) DEFAULT NULL,
                    status VARCHAR(50) DEFAULT NULL,
                    progress INT DEFAULT NULL,
                    last_sync_at DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE INDEX UNIQ_1099A098135DB5A6 (smartsheet_row_id),
                    INDEX IDX_1099A098166D1F9C (project_id),
                    INDEX IDX_1099A098FFFE75C0 (parent_task_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE smartsheet_tasks ADD CONSTRAINT FK_1099A098166D1F9C FOREIGN KEY (project_id) REFERENCES smartsheet_projects (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE smartsheet_tasks ADD CONSTRAINT FK_1099A098FFFE75C0 FOREIGN KEY (parent_task_id) REFERENCES smartsheet_tasks (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('sprints')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE sprints (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    goal LONGTEXT DEFAULT NULL,
                    start_date DATE DEFAULT NULL,
                    end_date DATE DEFAULT NULL,
                    status VARCHAR(20) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }

        if (!$schema->hasTable('issue_labels')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_labels (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    color VARCHAR(7) NOT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY(id),
                    UNIQUE INDEX UNIQ_472188365E237E06 (name)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }

        if (!$schema->hasTable('issues')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issues (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    issue_number VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description LONGTEXT DEFAULT NULL,
                    issue_type VARCHAR(50) NOT NULL,
                    priority VARCHAR(50) NOT NULL,
                    severity VARCHAR(50) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    resolution VARCHAR(50) DEFAULT NULL,
                    project_id BIGINT DEFAULT NULL,
                    task_id BIGINT DEFAULT NULL,
                    reporter_id INT NOT NULL,
                    assignee_id INT DEFAULT NULL,
                    epic_id BIGINT DEFAULT NULL,
                    sprint_id BIGINT DEFAULT NULL,
                    due_date DATE DEFAULT NULL,
                    task_due_date DATE DEFAULT NULL,
                    estimated_hours NUMERIC(10, 2) DEFAULT NULL,
                    actual_hours NUMERIC(10, 2) DEFAULT NULL,
                    labels JSON DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    resolved_at DATETIME DEFAULT NULL,
                    closed_at DATETIME DEFAULT NULL,
                    UNIQUE INDEX UNIQ_DA7D7F8364609573 (issue_number),
                    INDEX IDX_DA7D7F83166D1F9C (project_id),
                    INDEX IDX_DA7D7F838DB60186 (task_id),
                    INDEX IDX_DA7D7F83E1CFE6F5 (reporter_id),
                    INDEX IDX_DA7D7F8359EC7D60 (assignee_id),
                    INDEX IDX_DA7D7F836B71E00E (epic_id),
                    INDEX IDX_DA7D7F838C24077B (sprint_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issues ADD CONSTRAINT FK_DA7D7F83166D1F9C FOREIGN KEY (project_id) REFERENCES smartsheet_projects (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE issues ADD CONSTRAINT FK_DA7D7F838DB60186 FOREIGN KEY (task_id) REFERENCES smartsheet_tasks (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE issues ADD CONSTRAINT FK_DA7D7F83E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES `user` (id)');
            $this->addSql('ALTER TABLE issues ADD CONSTRAINT FK_DA7D7F8359EC7D60 FOREIGN KEY (assignee_id) REFERENCES `user` (id)');
            $this->addSql('ALTER TABLE issues ADD CONSTRAINT FK_DA7D7F836B71E00E FOREIGN KEY (epic_id) REFERENCES issues (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE issues ADD CONSTRAINT FK_DA7D7F838C24077B FOREIGN KEY (sprint_id) REFERENCES sprints (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('issue_label_map')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_label_map (
                    issue_id BIGINT NOT NULL,
                    issue_label_id BIGINT NOT NULL,
                    PRIMARY KEY(issue_id, issue_label_id),
                    INDEX IDX_E69C21455E7AA58C (issue_id),
                    INDEX IDX_E69C2145E4110592 (issue_label_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issue_label_map ADD CONSTRAINT FK_E69C21455E7AA58C FOREIGN KEY (issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_label_map ADD CONSTRAINT FK_E69C2145E4110592 FOREIGN KEY (issue_label_id) REFERENCES issue_labels (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('issue_comments')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_comments (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    issue_id BIGINT NOT NULL,
                    user_id INT NOT NULL,
                    edited_by_id INT DEFAULT NULL,
                    comment LONGTEXT NOT NULL,
                    is_internal TINYINT(1) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX IDX_8836BC815E7AA58C (issue_id),
                    INDEX IDX_8836BC81A76ED395 (user_id),
                    INDEX IDX_8836BC81DD7B2EBC (edited_by_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issue_comments ADD CONSTRAINT FK_8836BC815E7AA58C FOREIGN KEY (issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_comments ADD CONSTRAINT FK_8836BC81A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
            $this->addSql('ALTER TABLE issue_comments ADD CONSTRAINT FK_8836BC81DD7B2EBC FOREIGN KEY (edited_by_id) REFERENCES `user` (id)');
        }

        if (!$schema->hasTable('issue_attachments')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_attachments (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    issue_id BIGINT NOT NULL,
                    user_id INT NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    original_filename VARCHAR(255) NOT NULL,
                    filepath VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(255) DEFAULT NULL,
                    file_size BIGINT DEFAULT NULL,
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX IDX_258FAF725E7AA58C (issue_id),
                    INDEX IDX_258FAF72A76ED395 (user_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issue_attachments ADD CONSTRAINT FK_258FAF725E7AA58C FOREIGN KEY (issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_attachments ADD CONSTRAINT FK_258FAF72A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        }

        if (!$schema->hasTable('issue_activity')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_activity (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    issue_id BIGINT NOT NULL,
                    user_id INT DEFAULT NULL,
                    activity_type VARCHAR(50) NOT NULL,
                    field_name VARCHAR(100) DEFAULT NULL,
                    old_value LONGTEXT DEFAULT NULL,
                    new_value LONGTEXT DEFAULT NULL,
                    comment LONGTEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX IDX_7BDC23F15E7AA58C (issue_id),
                    INDEX IDX_7BDC23F1A76ED395 (user_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issue_activity ADD CONSTRAINT FK_7BDC23F15E7AA58C FOREIGN KEY (issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_activity ADD CONSTRAINT FK_7BDC23F1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        }

        if (!$schema->hasTable('issue_watchers')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_watchers (
                    issue_id BIGINT NOT NULL,
                    user_id INT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY(issue_id, user_id),
                    INDEX IDX_13748585A76ED395 (user_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issue_watchers ADD CONSTRAINT FK_137485855E7AA58C FOREIGN KEY (issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_watchers ADD CONSTRAINT FK_13748585A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('issue_links')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE issue_links (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    source_issue_id BIGINT NOT NULL,
                    target_issue_id BIGINT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    link_type VARCHAR(50) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX IDX_4308C84B1D5A935E (source_issue_id),
                    INDEX IDX_4308C84B84D175E5 (target_issue_id),
                    INDEX IDX_4308C84BB03A8386 (created_by_id),
                    UNIQUE INDEX UNIQ_ISSUE_LINK (source_issue_id, target_issue_id, link_type),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE issue_links ADD CONSTRAINT FK_4308C84B1D5A935E FOREIGN KEY (source_issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_links ADD CONSTRAINT FK_4308C84B84D175E5 FOREIGN KEY (target_issue_id) REFERENCES issues (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE issue_links ADD CONSTRAINT FK_4308C84BB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        }

        if (!$schema->hasTable('smartsheet_sync_log')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE smartsheet_sync_log (
                    id BIGINT AUTO_INCREMENT NOT NULL,
                    sync_type VARCHAR(20) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    records_processed INT DEFAULT 0 NOT NULL,
                    records_added INT DEFAULT 0 NOT NULL,
                    records_updated INT DEFAULT 0 NOT NULL,
                    error_message LONGTEXT DEFAULT NULL,
                    started_at DATETIME NOT NULL,
                    completed_at DATETIME DEFAULT NULL,
                    INDEX IDX_SYNC_STARTED_AT (started_at),
                    INDEX IDX_SYNC_TYPE (sync_type),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS issue_activity');
        $this->addSql('DROP TABLE IF EXISTS issue_attachments');
        $this->addSql('DROP TABLE IF EXISTS issue_comments');
        $this->addSql('DROP TABLE IF EXISTS issue_label_map');
        $this->addSql('DROP TABLE IF EXISTS issue_labels');
        $this->addSql('DROP TABLE IF EXISTS issue_links');
        $this->addSql('DROP TABLE IF EXISTS issue_watchers');
        $this->addSql('DROP TABLE IF EXISTS issues');
        $this->addSql('DROP TABLE IF EXISTS smartsheet_tasks');
        $this->addSql('DROP TABLE IF EXISTS smartsheet_projects');
        $this->addSql('DROP TABLE IF EXISTS smartsheet_sync_log');
        $this->addSql('DROP TABLE IF EXISTS sprints');
    }
}
