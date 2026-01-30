<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table to store POS invoice sequence numbers';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('ongleri', 'pos_invoice_number')) {
            return;
        }

        $this->addSql(<<<SQL
CREATE TABLE ongleri.pos_invoice_number (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY UNQ_invoice_order (order_id),
    CONSTRAINT FK_pos_invoice_order FOREIGN KEY (order_id) REFERENCES ongleri.orders (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('ongleri', 'pos_invoice_number')) {
            return;
        }

        $this->addSql('DROP TABLE ongleri.pos_invoice_number');
    }

    private function tableExists(string $schema, string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table';
        $count = (int) $this->connection->fetchOne($sql, ['schema' => $schema, 'table' => $table]);

        return $count > 0;
    }
}
