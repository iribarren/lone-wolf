<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Command\AppendNarrativeEntryCommand;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Journal\Domain\JournalEntry;
use App\Shared\Domain\ClockInterface;

/**
 * POST /api/campaigns/{id}/journal (FR-015): appends an immutable narrative
 * entry stamped with the campaign's CURRENT stage, so history groups by the
 * stage the story was actually lived in.
 */
final readonly class AppendNarrativeEntryHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private JournalEntryRepositoryInterface $journalEntries,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AppendNarrativeEntryCommand $command): JournalEntry
    {
        // Refuses unknown ids and foreign players identically (FR-019).
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($command->campaignId, $command->playerId);

        $entry = JournalEntry::writeNarrative(
            $command->campaignId,
            $campaign->position()->stageName,
            $command->narrative,
            $this->clock->now(),
        );

        $this->journalEntries->add($entry);

        return $entry;
    }
}
