<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/** PC or supporting-cast NPC — requirement sets differ (FR-021/FR-024). */
enum CharacterKind: string
{
    case Pc = 'pc';
    case Npc = 'npc';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new \InvalidArgumentException(sprintf('"%s" is not a character kind (pc|npc).', $value));
    }
}
