<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Characters\Application\Command\UpdateCharacterCommand;
use App\Characters\Application\Port\CharacterRepositoryInterface;
use App\Characters\Application\Port\SheetStructureProviderInterface;
use App\Characters\Domain\AttributeValidator;
use App\Characters\Domain\CharacterKind;
use App\Characters\Domain\SheetValidationException;
use App\Shared\Domain\Identifier\CharacterId;

/**
 * Character update (FR-023/FR-025): the incoming payload is revalidated
 * against the CURRENT structure — kind is immutable, so a PC stays a PC.
 * A conforming re-save resets drift state and re-stamps the version.
 */
final readonly class UpdateCharacterHandler
{
    public function __construct(
        private CharacterRepositoryInterface $characters,
        private SheetStructureProviderInterface $sheets,
        private CampaignRepositoryInterface $campaigns,
    ) {
    }

    public function handle(UpdateCharacterCommand $command): \App\Characters\Domain\Character
    {
        $character = $this->characters->get($command->characterId)
            ?? throw new CharacterNotFoundException();

        // FR-019 through the character's campaign: foreign ids read as unknown.
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($character->campaignId(), $command->playerId);

        if (CharacterKind::fromString($command->kind) !== $character->kind()) {
            throw new SheetValidationException([
                new \App\Characters\Domain\AttributeViolation('kind', 'A character\'s kind cannot change.'),
            ]);
        }

        $schema = $this->sheets->forSystem($campaign->gameSystemId())
            ?? throw new SystemHasNoSheetException();

        $violations = (new AttributeValidator())
            ->validate($command->attributes, $character->kind(), $schema);

        if ($violations !== []) {
            throw new SheetValidationException($violations);
        }

        $updated = $character->with(
            name: $command->name,
            attributes: $command->attributes,
            structureVersion: $schema->version,
        );

        $this->characters->add($updated);

        return $updated;
    }
}
