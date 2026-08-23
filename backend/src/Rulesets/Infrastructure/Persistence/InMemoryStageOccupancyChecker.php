<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Persistence;

use App\Rulesets\Application\Port\StageOccupancyCheckerInterface;
use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Zero campaigns exist before US2 ships, so every stage reports unoccupied.
 * The real Doctrine adapter (T047) replaces this binding.
 */
final class InMemoryStageOccupancyChecker implements StageOccupancyCheckerInterface
{
    /** @var array<string, array<string, true>> */
    private array $occupied = [];

    public function markOccupied(GameSystemId $systemId, string $stageName): void
    {
        $this->occupied[$systemId->toString()][$stageName] = true;
    }

    public function release(GameSystemId $systemId, string $stageName): void
    {
        unset($this->occupied[$systemId->toString()][$stageName]);
    }

    public function occupiedStages(GameSystemId $systemId): array
    {
        return array_keys($this->occupied[$systemId->toString()] ?? []);
    }
}
