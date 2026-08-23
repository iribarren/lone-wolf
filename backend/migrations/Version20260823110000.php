<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Campaigns + Journal tables for US2 (T047).
 *
 * - journal_entries carries the SC-008 covering index on
 *   (campaign_id, created_at DESC) — written by hand because the ORM schema
 *   tool cannot express descending indexes;
 * - the campaign FK cascades confirmed deletes into the journal (FR-020);
 * - campaigns(player_id) backs owner-scoped listing (FR-019).
 */
final class Version20260823110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Campaigns and Journal context tables: campaigns, journal_entries (FR-015..FR-020, SC-008).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE campaigns (id VARCHAR(36) NOT NULL, player_id VARCHAR(36) NOT NULL, game_system_id VARCHAR(36) NOT NULL, stage_name VARCHAR(120) NOT NULL, created_at TIMESTAMPTZ NOT NULL, updated_at TIMESTAMPTZ NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_campaigns_player ON campaigns (player_id)');
        $this->addSql('CREATE TABLE journal_entries (id VARCHAR(36) NOT NULL, campaign_id VARCHAR(36) NOT NULL, stage_name VARCHAR(120) NOT NULL, kind VARCHAR(16) NOT NULL, narrative TEXT DEFAULT NULL, oracle_snapshot JSONB DEFAULT NULL, roll_snapshot JSONB DEFAULT NULL, created_at TIMESTAMPTZ NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_journal_campaign_created_desc ON journal_entries (campaign_id, created_at DESC)');
        $this->addSql('ALTER TABLE journal_entries ADD CONSTRAINT fk_journal_entries_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE journal_entries DROP CONSTRAINT fk_journal_entries_campaign');
        $this->addSql('DROP TABLE campaigns');
        $this->addSql('DROP TABLE journal_entries');
    }
}
