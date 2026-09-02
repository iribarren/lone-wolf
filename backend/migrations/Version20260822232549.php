<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Intentional no-op, kept for the history's integrity (audit C5).
 *
 * `make:migration` emitted this stub against a schema that already matched
 * the mapping, so it never carried any SQL. It cannot simply be deleted: it
 * is recorded as executed in `doctrine_migration_versions`, and removing the
 * class would make `doctrine:migrations:status` report an unavailable
 * migration on every environment that has run it.
 */
final class Version20260822232549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Intentional no-op: an empty auto-generated stub, retained because it is recorded as executed.';
    }

    public function up(Schema $schema): void
    {
        // Deliberately empty — see the class docblock.
    }

    public function down(Schema $schema): void
    {
        // Deliberately empty — nothing to reverse.
    }
}
