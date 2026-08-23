<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Command;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/** POST /api/campaigns/{id}/advance — attempt a stage move along a legal transition (FR-016). */
final readonly class AdvanceStageCommand
{
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public string $toStageName,
    ) {
    }
}
