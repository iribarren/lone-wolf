<?php

declare(strict_types=1);

namespace App\Dice\Domain;

/**
 * Immutable NdM±K notation (data-model.md Dice Context): strict parsing with
 * typed pre-roll refusals — bounds N ∈ [1,50], M ∈ [2,1000],
 * K ∈ [-10000,+10000] (FR-026/FR-027, Edge Case §5). The canonical string
 * normalises optional whitespace so journal snapshots read consistently.
 */
final readonly class DiceNotation
{
    public const MIN_COUNT = 1;
    public const MAX_COUNT = 50;
    public const MIN_FACES = 2;
    public const MAX_FACES = 1000;
    public const MIN_MODIFIER = -10_000;
    public const MAX_MODIFIER = 10_000;

    private const PARSER_PATTERN = '/^(\d+)d(\d+)(?:\s*([+-])\s*(\d+))?$/';

    /**
     * Digit runs longer than this can never be in bounds, and casting them to
     * int would silently overflow on 64-bit platforms — refuse them early.
     */
    private const MAX_DICE_DIGITS = 9;
    private const MAX_MODIFIER_DIGITS = 5;

    private function __construct(
        private int $count,
        private int $faces,
        private int $modifier,
        private string $canonical,
    ) {
    }

    /**
     * @throws InvalidDiceNotationException With the specific refusal reason.
     */
    public static function fromString(string $input): self
    {
        $trimmed = trim($input);

        if (preg_match(self::PARSER_PATTERN, $trimmed, $matches) !== 1) {
            throw InvalidDiceNotationException::malformed($trimmed);
        }

        $count = self::parseWithin($matches[1], self::MAX_DICE_DIGITS);

        if ($count === 0) {
            throw InvalidDiceNotationException::invalidCount();
        }

        if ($count < self::MIN_COUNT || $count > self::MAX_COUNT) {
            throw InvalidDiceNotationException::outOfBounds();
        }

        $faces = self::parseWithin($matches[2], self::MAX_DICE_DIGITS);

        if ($faces < self::MIN_FACES) {
            throw InvalidDiceNotationException::invalidFaces();
        }

        if ($faces > self::MAX_FACES) {
            throw InvalidDiceNotationException::outOfBounds();
        }

        $sign = $matches[3] ?? null;
        $magnitudeDigits = $matches[4] ?? null;

        if (!is_string($sign) || !is_string($magnitudeDigits)) {
            return new self($count, $faces, 0, self::canonicalize($count, $faces, 0));
        }

        $magnitude = self::parseWithin($magnitudeDigits, self::MAX_MODIFIER_DIGITS);
        $modifier = $sign === '-' ? -$magnitude : $magnitude;

        if ($modifier < self::MIN_MODIFIER || $modifier > self::MAX_MODIFIER) {
            throw InvalidDiceNotationException::outOfBounds();
        }

        return new self($count, $faces, $modifier, self::canonicalize($count, $faces, $modifier));
    }

    /** Number of dice to roll. */
    public function count(): int
    {
        return $this->count;
    }

    /** Faces per die. */
    public function faces(): int
    {
        return $this->faces;
    }

    /** Flat modifier added to the sum of dice (0 when absent). */
    public function modifier(): int
    {
        return $this->modifier;
    }

    /** Canonical `NdM±K` rendering used across API payloads and journals. */
    public function toString(): string
    {
        return $this->canonical;
    }

    public function __toString(): string
    {
        return $this->canonical;
    }

    private static function canonicalize(int $count, int $faces, int $modifier): string
    {
        if ($modifier === 0) {
            return sprintf('%dd%d', $count, $faces);
        }

        return sprintf('%dd%d%+d', $count, $faces, $modifier);
    }

    /**
     * Casts a digit run to int only when it cannot overflow; anything longer
     * is out of bounds by definition (every supported bound is far smaller).
     */
    private static function parseWithin(string $digits, int $maxLength): int
    {
        if (strlen($digits) > $maxLength) {
            // Sentinel far beyond every bound; callers reject it next.
            return \PHP_INT_MAX;
        }

        return (int) $digits;
    }
}
