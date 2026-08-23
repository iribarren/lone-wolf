<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Api\Input;

/**
 * POST /api/campaigns/{id}/characters and PATCH /api/characters/{id} body
 * (contract CharacterWrite). Attributes arrive as the raw keyed payload —
 * conformity is judged by the domain validator, not Symfony forms.
 */
final readonly class SaveCharacterInput
{
    /**
     * @param array<string, mixed>|null $attributes
     */
    public function __construct(
        public string $kind = 'pc',
        public string $name = '',
        public ?array $attributes = null,
    ) {
    }
}
