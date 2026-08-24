<?php

declare(strict_types=1);

namespace App\Dice\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Dice\Application\Command\RollAndLogToJournal;
use App\Dice\Domain\InvalidDiceNotationException;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Journal\Domain\JournalEntry;
use App\Journal\Domain\RollSnapshot;
use App\Shared\Domain\ClockInterface;

/**
 * POST /api/campaigns/{campaignId}/rolls (FR-029): rolls the notation and
 * appends a dice_roll entry stamped with the campaign's CURRENT stage.
 * Ownership (FR-019) is enforced through the shared owned-campaign fetcher
 * before any die is thrown; invalid notation is refused pre-roll (FR-027).
 */
final readonly class RollAndLogHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private JournalEntryRepositoryInterface $journalEntries,
        private RollDiceHandler $rollDice,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidDiceNotationException                    Refused pre-roll, with reason.
     * @throws \App\Campaigns\Domain\CampaignNotFoundException Unknown and foreign campaigns refuse identically (FR-019).
     */
    public function handle(RollAndLogToJournal $command): LoggedRoll
    {
        // Refuses unknown ids and foreign players identically (FR-019).
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($command->campaignId, $command->playerId);

        $roll = $this->rollDice->handle($command->notation);

        $entry = JournalEntry::recordDiceRoll(
            $command->campaignId,
            $campaign->position()->stageName,
            new RollSnapshot(
                $roll->notation()->toString(),
                $roll->diceValues(),
                $roll->modifier(),
                $roll->total(),
            ),
            $this->clock->now(),
        );

        $this->journalEntries->add($entry);

        return new LoggedRoll($roll, $entry);
    }
}
