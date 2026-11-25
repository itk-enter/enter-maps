<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the app_user table for the users of the builder.
 */
final class Version20261125130346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Added the app_user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id BINARY(16) NOT NULL, name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, last_login_at DATETIME NOT NULL, issuer VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX app_user_issuer_subject (issuer, subject), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_user');
    }
}
