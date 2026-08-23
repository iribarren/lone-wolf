<?php

declare(strict_types=1);

namespace App\Characters\Application\Query;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/** GET /api/campaigns/{id}/characters (FR-019 owner-scoped). */
final readonly class ListCharactersQuery
{
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
    ) {
    }
}
