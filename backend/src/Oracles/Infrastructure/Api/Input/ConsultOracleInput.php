<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api\Input;

/**
 * POST /api/campaigns/{campaignId}/oracles/{oracleId}/consult body.
 * `save` persists a selected result into the journal in the same round trip
 * (US4 scenario 3); omitted/false consults only.
 */
final readonly class ConsultOracleInput
{
    public function __construct(
        public bool $save = false,
    ) {
    }
}
