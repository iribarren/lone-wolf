<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Query;

/**
 * Player-facing system summary (contract SystemSummary).
 */
final readonly class SystemSummary
{
    public function __construct(
        public string $systemId,
        public string $name,
        public string $description,
        public string $startingStage,
        public string $openingGuidance,
    ) {
    }
}
