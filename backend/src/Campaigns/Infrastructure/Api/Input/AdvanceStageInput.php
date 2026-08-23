<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Input;

/**
 * POST /api/campaigns/{campaignId}/advance body (contract: {toStageId}).
 * The value is the target stage's name-keyed identity (US1 decision).
 */
final readonly class AdvanceStageInput
{
    public function __construct(
        public string $toStageId = '',
    ) {
    }
}
