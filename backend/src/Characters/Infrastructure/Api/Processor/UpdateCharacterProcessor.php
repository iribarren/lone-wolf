<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Characters\Application\Command\UpdateCharacterCommand;
use App\Characters\Application\ListCharactersHandler;
use App\Characters\Application\Query\ListCharactersQuery;
use App\Characters\Application\UpdateCharacterHandler;
use App\Characters\Domain\Character;
use App\Characters\Infrastructure\Api\CharacterResource;
use App\Characters\Infrastructure\Api\Input\SaveCharacterInput;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * PATCH /api/characters/{characterId} (FR-023/FR-025): revalidated against
 * the CURRENT structure; a conforming save resets drift. Ownership runs
 * through the character's campaign inside the handler — foreign ids read
 * as unknown.
 *
 * @implements ProcessorInterface<SaveCharacterInput, CharacterResource>
 */
final readonly class UpdateCharacterProcessor implements ProcessorInterface
{
    public function __construct(
        private UpdateCharacterHandler $handler,
        private ListCharactersHandler $projection,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CharacterResource
    {
        \assert($data instanceof SaveCharacterInput);

        $rawCharacterId = $uriVariables['characterId'] ?? '';
        \assert(is_string($rawCharacterId));

        /** @var array<string, mixed> $attributes */
        $attributes = $data->attributes ?? [];

        $updated = $this->handler->handle(new UpdateCharacterCommand(
            $this->currentUser->currentUserId(),
            \App\Shared\Domain\Identifier\CharacterId::fromString($rawCharacterId),
            $data->kind,
            $data->name,
            $attributes,
        ));

        // The campaign id is needed for the read-side projection; resolve it
        // from the updated aggregate rather than trusting route input.
        $campaignId = $updated->campaignId();

        foreach ($this->projection->handle(new ListCharactersQuery(
            $this->currentUser->currentUserId(),
            $campaignId,
        )) as $view) {
            if ($view->id === $updated->id()->toString()) {
                return \App\Characters\Infrastructure\Api\Provider\CharactersProvider::fromData($view);
            }
        }

        throw new \RuntimeException('The updated character could not be projected.');
    }
}
