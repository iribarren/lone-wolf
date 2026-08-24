<?php

declare(strict_types=1);

namespace App\Dice\Domain;

/**
 * A refused notation (FR-026): thrown BEFORE any die is rolled, carrying the
 * specific reason (FR-027) so no fake or partial result can ever exist.
 */
final class InvalidDiceNotationException extends \InvalidArgumentException
{
    public function __construct(
        private readonly DiceNotationFailureReason $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function reason(): DiceNotationFailureReason
    {
        return $this->reason;
    }

    public static function malformed(string $input): self
    {
        return new self(
            DiceNotationFailureReason::Malformed,
            sprintf('"%s" is not standard NdM±K dice notation.', $input),
        );
    }

    public static function invalidCount(): self
    {
        return new self(
            DiceNotationFailureReason::InvalidCount,
            'The die count must be at least 1.',
        );
    }

    public static function invalidFaces(): self
    {
        return new self(
            DiceNotationFailureReason::InvalidFaces,
            sprintf('A die needs at least %d faces.', DiceNotation::MIN_FACES),
        );
    }

    public static function outOfBounds(): self
    {
        return new self(
            DiceNotationFailureReason::OutOfBounds,
            sprintf(
                'Dice notation is bounded to N ∈ [1,%d], M ∈ [2,%d], K ∈ [%d,+%d].',
                DiceNotation::MAX_COUNT,
                DiceNotation::MAX_FACES,
                DiceNotation::MIN_MODIFIER,
                DiceNotation::MAX_MODIFIER,
            ),
        );
    }
}
