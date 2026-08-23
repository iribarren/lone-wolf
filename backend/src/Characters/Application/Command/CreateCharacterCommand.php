<?php

declare(strict_types=1);

namespace App\Characters\Application\Command;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * POST /api/campaigns/{id}/characters (contract CharacterWrite).
 */
final readonly class CreateCharacterCommand
{
    /**
     * @param array<string, mixed> $attributes raw payload keyed by sheet field
     */
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public string $kind,
        public string $name,
        public array $attributes,
    ) {
    }
}
