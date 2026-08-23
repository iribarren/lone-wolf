<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Input;

/**
 * POST /api/campaigns body (contract: {gameSystemId}).
 */
final readonly class StartCampaignInput
{
    public function __construct(
        public string $gameSystemId = '',
    ) {
    }
}
