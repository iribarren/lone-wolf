<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Command;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/** DELETE /api/campaigns/{id}?confirm=true — irreversible removal (FR-020). */
final readonly class DeleteCampaignCommand
{
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public bool $confirm = false,
    ) {
    }
}
