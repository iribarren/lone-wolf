<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Api\Input;

/**
 * POST /api/campaigns/{campaignId}/journal body (contract: {narrative}).
 */
final readonly class AppendNarrativeInput
{
    public function __construct(
        public string $narrative = '',
    ) {
    }
}
