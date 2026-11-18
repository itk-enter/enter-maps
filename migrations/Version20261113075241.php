<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the map_configuration table.
 */
final class Version20261113075241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added the map_configuration table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE map_configuration (id BINARY(16) NOT NULL, config JSON NOT NULL, renderers JSON NOT NULL, map_json JSON NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE map_configuration');
    }
}
