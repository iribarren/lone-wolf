<?php

declare(strict_types=1);

namespace App\Dice\Application;

use App\Dice\Domain\DiceNotation;
use App\Dice\Domain\DiceRoll;
use App\Dice\Domain\DiceRoller;
use App\Dice\Domain\InvalidDiceNotationException;
use App\Shared\Domain\ClockInterface;

/**
 * POST /api/dice/roll (FR-026..028): parses strictly and rolls once.
 * Invalid input is refused BEFORE any die is thrown (FR-027) — the typed
 * exception carries the contract's failure reason.
 */
final readonly class RollDiceHandler
{
    public function __construct(
        private DiceRoller $roller,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidDiceNotationException Pre-roll, with the specific reason.
     */
    public function handle(string $notation): DiceRoll
    {
        return $this->roller->roll(DiceNotation::fromString($notation), $this->clock);
    }
}
