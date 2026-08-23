<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Characters\Application\ListCharactersHandler;
use App\Characters\Application\Query\CharacterData;
use App\Characters\Application\Query\ListCharactersQuery;
use App\Characters\Infrastructure\Api\CharacterResource;
use App\Characters\Infrastructure\Api\SheetFieldEntryResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * GET /api/campaigns/{campaignId}/characters (FR-019 owner-scoped).
 *
 * @implements ProviderInterface<CharacterResource>
 */
final readonly class CharactersProvider implements ProviderInterface
{
    public function __construct(
        private ListCharactersHandler $handler,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return list<CharacterResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        $data = $this->handler->handle(new ListCharactersQuery(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
        ));

        return array_map(self::fromData(...), $data);
    }

    public static function fromData(CharacterData $data): CharacterResource
    {
        return new CharacterResource(
            $data->id,
            $data->kind,
            $data->name,
            $data->attributes,
            $data->validatedStructureVersion,
            $data->reviewStatus,
            $data->driftIssues,
            $data->structure?->version,
            $data->structure === null ? null : array_map(
                static fn ($field): SheetFieldEntryResource => new SheetFieldEntryResource(
                    $field->key,
                    $field->label,
                    $field->type,
                    $field->requiredForPc,
                    $field->requiredForNpc,
                    $field->options,
                ),
                $data->structure->fields,
            ),
        );
    }
}
