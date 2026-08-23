<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Where a campaign currently stands inside its system's flow.
 *
 * Stages are identified by their authored name (Rulesets keeps stage names
 * unique within a flow), so a position is a (system, stage-name) pair that
 * survives serialization without surrogate keys.
 */
final readonly class StagePosition
{
    public function __construct(
        public GameSystemId $gameSystemId,
        public string $stageName,
    ) {
        if (trim($this->stageName) === '') {
            throw new \InvalidArgumentException('A campaign must always sit on a named stage.');
        }
    }

    public function movedTo(string $stageName): self
    {
        return new self($this->gameSystemId, $stageName);
    }

    public function isAt(string $stageName): bool
    {
        return $this->stageName === $stageName;
    }
}
