<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api\Input;

/**
 * POST /api/campaigns/{campaignId}/oracles/{oracleId}/save body:
 * journals the already-shown result; `interpretation` optionally lands as a
 * follow-up narrative entry (US4 "save with interpretation").
 */
final readonly class SaveConsultationInput
{
    public function __construct(
        public string $text = '',
        public string $interpretation = '',
    ) {
    }
}
