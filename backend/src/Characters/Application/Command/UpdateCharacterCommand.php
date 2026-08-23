<?php

declare(strict_types=1);

namespace App\Characters\Application\Command;

use App\Shared\Domain\Identifier\CharacterId;
use App\Shared\Domain\Identifier\UserId;

/**
 * PATCH /api/characters/{characterId} (contract CharacterWrite): name and
 * attributes are revalidated against the CURRENT structure (FR-025).
 */
final readonly class UpdateCharacterCommand
{
    /**
     * @param array<string, mixed> $attributes raw payload keyed by sheet field
     */
    public function __construct(
        public UserId $playerId,
        public CharacterId $characterId,
        public string $kind,
        public string $name,
        public array $attributes,
    ) {
    }
}
