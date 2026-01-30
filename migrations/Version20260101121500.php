<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename YouTube accounting category to Abonnement and retag Canva entries.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('ongleri', 'accounting_categories') || !$this->tableExists('ongleri', 'accounting_entries')) {
            return;
        }

        $youtube = $this->connection->fetchAssociative(
            "SELECT id, code FROM ongleri.accounting_categories WHERE name = 'YouTube' LIMIT 1"
        );
        $abonnement = $this->connection->fetchAssociative(
            "SELECT id, code FROM ongleri.accounting_categories WHERE name = 'Abonnement' LIMIT 1"
        );

        $targetCategoryId = null;

        if ($youtube && $abonnement && (int)$youtube['id'] !== (int)$abonnement['id']) {
            $targetCategoryId = (int)$abonnement['id'];

            $this->addSql(
                'UPDATE ongleri.accounting_entries SET category_id = :target WHERE category_id = :legacy',
                ['target' => $targetCategoryId, 'legacy' => (int)$youtube['id']]
            );

            $this->addSql(
                'DELETE FROM ongleri.accounting_categories WHERE id = :legacy',
                ['legacy' => (int)$youtube['id']]
            );
        } elseif ($youtube) {
            $targetCategoryId = (int)$youtube['id'];

            $desiredCode = 'ABONNEMENT';
            if ($this->codeExists($desiredCode, $targetCategoryId)) {
                $desiredCode = (string)($youtube['code'] ?? $desiredCode);
            }

            $this->addSql(
                'UPDATE ongleri.accounting_categories SET name = :name, code = :code WHERE id = :id',
                [
                    'name' => 'Abonnement',
                    'code' => $desiredCode,
                    'id' => $targetCategoryId,
                ]
            );
        } elseif ($abonnement) {
            $targetCategoryId = (int)$abonnement['id'];
        }

        if ($targetCategoryId !== null) {
            $this->assignCanvaEntries($targetCategoryId);
        }
    }

    public function down(Schema $schema): void
    {
        // No automated downgrade. Manual intervention would be required to restore previous names and assignments.
    }

    private function assignCanvaEntries(int $categoryId): void
    {
        $this->addSql(
            "UPDATE ongleri.accounting_entries SET category_id = :category WHERE label LIKE '%Canva%'",
            ['category' => $categoryId]
        );
    }

    private function tableExists(string $schema, string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table';
        $count = (int)$this->connection->fetchOne($sql, ['schema' => $schema, 'table' => $table]);

        return $count > 0;
    }

    private function codeExists(string $code, int $exceptId): bool
    {
        $sql = 'SELECT COUNT(*) FROM ongleri.accounting_categories WHERE code = :code AND id <> :id';
        $count = (int)$this->connection->fetchOne($sql, ['code' => $code, 'id' => $exceptId]);

        return $count > 0;
    }
}
