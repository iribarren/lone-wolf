<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Port;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Answers which flow stages of a system currently host at least one campaign
 * (FR-005). Implemented in-memory until US2 introduces campaigns.
 */
interface StageOccupancyCheckerInterface
{
    /** @return list<string> */
    public function occupiedStages(GameSystemId $systemId): array;
}
