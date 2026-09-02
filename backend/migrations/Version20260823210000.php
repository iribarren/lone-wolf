<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Oracles context table for US3 (T061).
 *
 * - scope discriminator columns (scope_type + nullable scope_system_id)
 *   implement FR-008's exactly-one-system-or-global rule;
 * - the partial unique index ON (scope_system_id) WHERE scope_type='system'
 *   guards system-scope integrity at the storage boundary (T057);
 * - idx_oracles_scope backs the FR-009 visibility query
 *   (scope_type='global' OR scope_system_id = :system).
 */
final class Version20260823210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Oracles context: oracles table with scope discriminator, partial unique index and scope lookup index (FR-007..FR-009).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE oracles (id VARCHAR(36) NOT NULL, title VARCHAR(160) NOT NULL, scope_type VARCHAR(16) NOT NULL, scope_system_id VARCHAR(36) DEFAULT NULL, entries JSONB NOT NULL, PRIMARY KEY (id))");
        $this->addSql('CREATE INDEX idx_oracles_scope ON oracles (scope_type, scope_system_id)');
        $this->addSql("CREATE UNIQUE INDEX uniq_oracles_scope_system ON oracles (scope_system_id) WHERE scope_type = 'system'");
    }

    public function down(Schema $schema): void
    {
        // Reverse order of up(), one DROP per CREATE (audit C5).
        $this->addSql('DROP INDEX uniq_oracles_scope_system');
        $this->addSql('DROP INDEX idx_oracles_scope');
        $this->addSql('DROP TABLE oracles');
    }
}
