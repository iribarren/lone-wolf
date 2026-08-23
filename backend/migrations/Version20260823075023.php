<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260823075023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_systems (id VARCHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, description TEXT NOT NULL, status VARCHAR(16) NOT NULL, flow_definition JSONB NOT NULL, sheet_structure JSONB DEFAULT NULL, version INT DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_game_systems_name ON game_systems (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE game_systems');
        $this->addSql('DROP INDEX uniq_users_email');
    }
}
