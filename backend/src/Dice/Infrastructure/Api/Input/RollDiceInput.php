<?php

declare(strict_types=1);

namespace App\Dice\Infrastructure\Api\Input;

/**
 * Shared body of POST /api/dice/roll and POST /api/campaigns/{campaignId}/rolls:
 * just the strict NdM±K notation string (contract RollRequest).
 */
final readonly class RollDiceInput
{
    public function __construct(
        public string $notation = '',
    ) {
    }
}
