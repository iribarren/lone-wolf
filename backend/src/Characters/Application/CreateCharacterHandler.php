<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Characters\Application\Command\CreateCharacterCommand;
use App\Characters\Application\Port\CharacterRepositoryInterface;
use App\Characters\Application\Port\SheetStructureProviderInterface;
use App\Characters\Domain\AttributeValidator;
use App\Characters\Domain\AttributesMap;
use App\Characters\Domain\Character;
use App\Characters\Domain\SheetValidationException;

/**
 * Character creation (FR-022..FR-024): validates against the owning
 * system's CURRENT structure and refuses violations with field-level
 * messages — no partial save. Conforming characters start clean, stamped
 * with the schema version they were validated against.
 */
final readonly class CreateCharacterHandler
{
    public function __construct(
        private CharacterRepositoryInterface $characters,
        private SheetStructureProviderInterface $sheets,
        private CampaignRepositoryInterface $campaigns,
    ) {
    }

    public function handle(CreateCharacterCommand $command): Character
    {
        // Refuses unknown ids and foreign players identically (FR-019).
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($command->campaignId, $command->playerId);

        $schema = $this->sheets->forSystem($campaign->gameSystemId())
            ?? throw new SystemHasNoSheetException();

        $kind = \App\Characters\Domain\CharacterKind::fromString($command->kind);

        $violations = (new AttributeValidator())->validate($command->attributes, $kind, $schema);

        if ($violations !== []) {
            throw new SheetValidationException($violations);
        }

        $character = Character::create(
            $command->campaignId,
            $kind,
            $command->name,
            AttributesMap::fromArray($command->attributes),
            $schema->version,
        );

        $this->characters->add($character);

        return $character;
    }
}
