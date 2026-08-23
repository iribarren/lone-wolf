<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Command;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/** POST /api/campaigns/{id}/journal — append narrative against the CURRENT stage (FR-015). */
final readonly class AppendNarrativeEntryCommand
{
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public string $narrative,
    ) {
    }
}
