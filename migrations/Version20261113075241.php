<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the map_configuration table, with a ULID primary key and the columns
 * of the timestampable and soft delete traits of itk-dev/entity-bundle.
 *
 * Written against the Schema API of Doctrine (no raw addSql), so it works on
 * each database that Doctrine supports.
 */
final class Version20261113075241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added the map_configuration table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('map_configuration');
        $table->addColumn('id', 'ulid', ['notnull' => true]);
        $table->addColumn('config', Types::JSON_OBJECT, ['notnull' => true]);
        $table->addColumn('renderers', Types::JSON, ['notnull' => true]);
        $table->addColumn('map_json', Types::JSON_OBJECT, ['notnull' => true]);
        $table->addColumn('deleted_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addOption('charset', 'utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('map_configuration');
    }
}
