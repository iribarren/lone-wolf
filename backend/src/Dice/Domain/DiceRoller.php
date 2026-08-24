<?php

declare(strict_types=1);

namespace App\Dice\Domain;

use App\Shared\Domain\ClockInterface;
use App\Shared\Domain\RandomSourceInterface;

/**
 * Rolls parsed notations through the injected RandomSource (Constitution IV)
 * so tests seed deterministic batches; time arrives via the Clock port.
 */
final readonly class DiceRoller
{
    public function __construct(private RandomSourceInterface $random)
    {
    }

    public function roll(DiceNotation $notation, ClockInterface $clock): DiceRoll
    {
        return DiceRoll::roll($notation, $this->random, $clock->now());
    }
}
