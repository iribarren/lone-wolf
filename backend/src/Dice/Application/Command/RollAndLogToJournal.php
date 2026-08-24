<?php

declare(strict_types=1);

namespace App\Dice\Application\Command;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * POST /api/campaigns/{campaignId}/rolls body (FR-029): roll the notation
 * AND append it to the campaign journal as a dice_roll record.
 */
final readonly class RollAndLogToJournal
{
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public string $notation,
    ) {
    }
}
