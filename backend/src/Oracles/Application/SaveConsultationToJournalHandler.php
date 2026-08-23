<?php

declare(strict_types=1);

namespace App\Oracles\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Journal\Domain\JournalEntry;
use App\Journal\Domain\OracleSnapshot;
use App\Oracles\Application\Command\SaveConsultationToJournal;
use App\Shared\Domain\ClockInterface;

/**
 * US4: saves a consulted result into the journal (scenario 3). Ownership
 * (FR-019) is enforced by fetching the campaign through the shared
 * owned-campaign fetcher; the entry is stamped with the campaign's CURRENT
 * stage exactly like narrative entries.
 */
final readonly class SaveConsultationToJournalHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private JournalEntryRepositoryInterface $journalEntries,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SaveConsultationToJournal $command): JournalEntry
    {
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($command->campaignId, $command->playerId);

        $entry = JournalEntry::recordOracleResult(
            $command->campaignId,
            $campaign->position()->stageName,
            new OracleSnapshot($command->oracleTitle, $command->resultText),
            $this->clock->now(),
        );

        $this->journalEntries->add($entry);

        return $entry;
    }
}
