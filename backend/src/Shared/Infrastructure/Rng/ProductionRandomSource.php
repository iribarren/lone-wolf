<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Rng;

use App\Shared\Domain\RandomSourceInterface;

/**
 * Production randomness backed by PHP's CSPRNG.
 */
final class ProductionRandomSource implements RandomSourceInterface
{
    #[\Override]
    public function intBetween(int $min, int $max): int
    {
        if ($min > $max) {
            throw new \InvalidArgumentException("Minimum {$min} cannot exceed maximum {$max}.");
        }

        return random_int($min, $max);
    }
}
