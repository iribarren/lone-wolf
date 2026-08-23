<?php

declare(strict_types=1);

namespace App\Oracles\Application\Query;

/**
 * Contract-facing oracle summary (contract schema OracleSummary).
 */
final readonly class OracleSummary
{
    public function __construct(
        public string $oracleId,
        public string $title,
        public string $scopeType,
        public int $entryCount,
    ) {
    }
}
