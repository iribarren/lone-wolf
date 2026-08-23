<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Injectable source of randomness (Constitution IV): domain code never calls
 * mt_rand/random_int directly so tests can seed deterministic fakes.
 */
interface RandomSourceInterface
{
    /**
     * Uniformly distributed integer between $min and $max, inclusive.
     *
     * @throws \InvalidArgumentException When $min > $max.
     */
    public function intBetween(int $min, int $max): int;
}
