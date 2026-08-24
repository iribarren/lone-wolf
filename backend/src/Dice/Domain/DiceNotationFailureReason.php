<?php

declare(strict_types=1);

namespace App\Dice\Domain;

/**
 * Typed pre-roll refusal reasons (FR-027): each maps onto the contract's
 * DiceNotationProblem reason enum so players learn exactly what was wrong
 * instead of receiving a generic error.
 */
enum DiceNotationFailureReason: string
{
    case Malformed = 'malformed';
    case InvalidCount = 'invalid_count';
    case InvalidFaces = 'invalid_faces';
    case OutOfBounds = 'out_of_bounds';
}
