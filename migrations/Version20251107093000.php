<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251107093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create psr_task_progress_log table for task progress protocol entries';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists($this->connection->getDatabase() ?? 'repweb', 'psr_task_progress_log')) {
            return;
        }

        $this->addSql("CREATE TABLE psr_task_progress_log (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            progress_pct SMALLINT UNSIGNED NOT NULL,
            note LONGTEXT NOT NULL,
            INDEX IDX_PSR_TASK_PROGRESS_TASK (task_id, created_at),
            CONSTRAINT FK_PSR_TASK_PROGRESS_TASK FOREIGN KEY (task_id) REFERENCES psr_task (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists($this->connection->getDatabase() ?? 'repweb', 'psr_task_progress_log')) {
            return;
        }

        $this->addSql('DROP TABLE psr_task_progress_log');
    }

    private function tableExists(string $schema, string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table';
        $count = (int) $this->connection->fetchOne($sql, [
            'schema' => $schema,
            'table' => $table,
        ]);

        return $count > 0;
    }
}
