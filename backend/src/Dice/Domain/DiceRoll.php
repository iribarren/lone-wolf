<?php

declare(strict_types=1);

namespace App\Dice\Domain;

use App\Shared\Domain\RandomSourceInterface;

/**
 * The outcome of one roll (data-model.md DiceRoll): individual die values,
 * the flat modifier, their modified total (FR-028) and the Clock timestamp.
 * Persisted only via journal snapshots — the Dice context stays stateless.
 */
final readonly class DiceRoll
{
    /**
     * @param list<int> $diceValues
     */
    private function __construct(
        private DiceNotation $notation,
        private array $diceValues,
        private int $modifier,
        private int $total,
        private \DateTimeImmutable $rolledAt,
    ) {
    }

    public static function roll(DiceNotation $notation, RandomSourceInterface $random, \DateTimeImmutable $rolledAt): self
    {
        $diceValues = [];

        for ($die = 0; $die < $notation->count(); ++$die) {
            $diceValues[] = $random->intBetween(1, $notation->faces());
        }

        $total = $notation->modifier();

        foreach ($diceValues as $value) {
            $total += $value;
        }

        return new self($notation, $diceValues, $notation->modifier(), $total, $rolledAt);
    }

    public function notation(): DiceNotation
    {
        return $this->notation;
    }

    /** @return list<int> */
    public function diceValues(): array
    {
        return $this->diceValues;
    }

    public function modifier(): int
    {
        return $this->modifier;
    }

    /** Σ diceValues ± modifier (FR-028). */
    public function total(): int
    {
        return $this->total;
    }

    public function rolledAt(): \DateTimeImmutable
    {
        return $this->rolledAt;
    }

    /**
     * Journal-ready snapshot shape (FR-029): `{notation, diceValues, modifier, total}`.
     *
     * @return array{notation: string, diceValues: list<int>, modifier: int, total: int}
     */
    public function snapshot(): array
    {
        return [
            'notation' => $this->notation->toString(),
            'diceValues' => $this->diceValues,
            'modifier' => $this->modifier,
            'total' => $this->total,
        ];
    }
}
