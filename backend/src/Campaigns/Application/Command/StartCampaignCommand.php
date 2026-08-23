<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Command;

use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;

/** POST /api/campaigns — bind a new campaign to an ACTIVE system (FR-012/FR-013). */
final readonly class StartCampaignCommand
{
    public function __construct(
        public UserId $playerId,
        public GameSystemId $gameSystemId,
    ) {
    }
}
