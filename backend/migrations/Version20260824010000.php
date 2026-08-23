<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Characters context table for US5 (T080).
 *
 * - attributes live in JSONB, shaped by the owning system's sheet
 *   structure (FR-022) and validated at write time (FR-023/FR-024);
 * - the campaign FK cascades on confirmed campaign deletion — a cast does
 *   not outlive its story (FR-020 parity with the journal);
 * - drift state is persisted but only ever projected on read until a
 *   conforming re-save resets it (FR-025).
 */
final class Version20260824010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Characters context: characters table with jsonb attributes, review/drift columns and campaign FK cascade (FR-021..FR-025).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE characters (id VARCHAR(36) NOT NULL, campaign_id VARCHAR(36) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(120) NOT NULL, attributes JSONB NOT NULL, validated_structure_version INTEGER NOT NULL, review_status VARCHAR(24) NOT NULL DEFAULT 'clean', drift_issues JSONB NOT NULL, PRIMARY KEY (id))");
        $this->addSql('CREATE INDEX idx_characters_campaign ON characters (campaign_id)');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT fk_characters_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE characters');
    }
}
