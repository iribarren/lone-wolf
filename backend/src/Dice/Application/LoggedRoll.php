<?php

declare(strict_types=1);

namespace App\Dice\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Dice\Application\Command\RollAndLogToJournal;
use App\Dice\Domain\DiceRoll;
use App\Journal\Domain\JournalEntry;
use App\Journal\Domain\RollSnapshot;

/**
 * Pair of the roll result and the journal entry that records it — the
 * `/campaigns/{id}/rolls` response payload (contract: 201 → {roll, journalEntry}).
 */
final readonly class LoggedRoll
{
    public function __construct(
        public DiceRoll $roll,
        public JournalEntry $entry,
    ) {
    }
}
