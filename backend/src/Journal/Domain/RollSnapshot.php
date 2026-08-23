<?php

declare(strict_types=1);

namespace App\Journal\Domain;

/**
 * Frozen dice-roll record ({notation, diceValues[], modifier, total}, FR-029)
 * so the journal shows exactly what was rolled at that moment.
 */
final readonly class RollSnapshot
{
    /**
     * @param list<int> $diceValues
     */
    public function __construct(
        public string $notation,
        public array $diceValues,
        public int $modifier,
        public int $total,
    ) {
        if (trim($this->notation) === '') {
            throw new \InvalidArgumentException('A roll snapshot needs its notation.');
        }

        if (\count($this->diceValues) === 0) {
            throw new \InvalidArgumentException('A roll snapshot needs at least one die value.');
        }
    }

    /** @return array{notation: string, diceValues: list<int>, modifier: int, total: int} */
    public function toArray(): array
    {
        return [
            'notation' => $this->notation,
            'diceValues' => $this->diceValues,
            'modifier' => $this->modifier,
            'total' => $this->total,
        ];
    }

    /**
     * @param array{notation?: mixed, diceValues?: mixed, modifier?: mixed, total?: mixed} $payload
     */
    public static function fromArray(array $payload): self
    {
        $notation = $payload['notation'] ?? '';
        $modifier = $payload['modifier'] ?? 0;
        $total = $payload['total'] ?? 0;
        $rawDice = $payload['diceValues'] ?? [];

        if (!is_string($notation) || !is_int($modifier) || !is_int($total) || !is_array($rawDice)) {
            throw new \InvalidArgumentException('A roll snapshot payload is malformed.');
        }

        $diceValues = [];
        foreach ($rawDice as $value) {
            if (!is_int($value)) {
                throw new \InvalidArgumentException('Roll snapshot die values must be integers.');
            }

            $diceValues[] = $value;
        }

        return new self($notation, $diceValues, $modifier, $total);
    }
}
