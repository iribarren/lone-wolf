<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns users.roles with the project's jsonb column type (audit C6).
 *
 * Every other document column in this schema — flow definitions, sheet
 * structures, oracle entries, journal snapshots, character attributes — is
 * declared through App\Shared\Infrastructure\Persistence\Types\JsonbType.
 * `roles` was the one column left on Doctrine's plain `json`, so schema
 * tooling reported a permanent diff against the mapping.
 *
 * The cast is value-preserving: PostgreSQL parses the stored text once and
 * re-stores it in the binary form. Key order inside an object is not
 * preserved by jsonb, which is irrelevant here — the column holds a JSON
 * array of role strings.
 */
final class Version20260902120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert users.roles from json to jsonb, matching the project-wide JsonbType (C6).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN roles TYPE JSONB USING roles::jsonb');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN roles TYPE JSON USING roles::json');
    }
}
