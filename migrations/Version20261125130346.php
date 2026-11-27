<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the app_user table for the users of the builder, with a ULID primary
 * key and the columns of the timestampable trait of itk-dev/entity-bundle.
 * The unique index on the issuer and the subject gives one row for each
 * person at a provider.
 *
 * Written against the Schema API of Doctrine (no raw addSql), so it works on
 * each database that Doctrine supports.
 */
final class Version20261125130346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added the app_user table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('app_user');
        $table->addColumn('id', 'ulid', ['notnull' => true]);
        $table->addColumn('issuer', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('subject', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('roles', Types::JSON, ['notnull' => true]);
        $table->addColumn('last_login_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['issuer', 'subject'], 'app_user_issuer_subject');
        $table->addOption('charset', 'utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('app_user');
    }
}
