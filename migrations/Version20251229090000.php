<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Types\Types;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251229090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create colette_entries table for Les escapade de Colette content.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('colette_entries')) {
            return;
        }

        $table = $schema->createTable('colette_entries');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('slug', Types::STRING, ['length' => 120]);
        $table->addColumn('title', Types::STRING, ['length' => 255]);
        $table->addColumn('category', Types::STRING, ['length' => 32]);
        $table->addColumn('subtitle', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('content', Types::TEXT);
        $table->addColumn('source_notes', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('is_published', Types::BOOLEAN);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['slug'], 'colette_entries_slug_idx');
        $table->addIndex(['category'], 'colette_entries_category_idx');
        $table->addIndex(['is_published'], 'colette_entries_published_idx');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('colette_entries')) {
            $schema->dropTable('colette_entries');
        }
    }
}
