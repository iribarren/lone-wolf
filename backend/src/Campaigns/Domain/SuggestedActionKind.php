<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * The two moves a player can be offered while playing (contract
 * SuggestedAction.kind): step along an existing transition, or wrap the
 * story up when no transition leaves the current stage.
 */
enum SuggestedActionKind
{
    case Advance;
    case Conclude;
}
