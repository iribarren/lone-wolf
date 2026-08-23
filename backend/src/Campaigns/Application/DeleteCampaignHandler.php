<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Command\DeleteCampaignCommand;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;

/**
 * DELETE /api/campaigns/{id}?confirm=true (FR-020): hard delete of the
 * aggregate plus its journal history — irreversible, therefore gated behind
 * an explicit confirm flag.
 */
final readonly class DeleteCampaignHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private JournalEntryRepositoryInterface $journalEntries,
    ) {
    }

    public function handle(DeleteCampaignCommand $command): void
    {
        if (!$command->confirm) {
            throw ConfirmationRequiredException::forCampaign($command->campaignId->toString());
        }

        (new OwnedCampaignFetcher($this->campaigns))->fetch($command->campaignId, $command->playerId);

        $this->journalEntries->deleteAllForCampaign($command->campaignId);
        $this->campaigns->delete($command->campaignId);
    }
}
