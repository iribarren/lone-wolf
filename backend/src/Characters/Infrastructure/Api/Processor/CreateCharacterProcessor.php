<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Characters\Application\Command\CreateCharacterCommand;
use App\Characters\Application\CreateCharacterHandler;
use App\Characters\Application\Query\ListCharactersQuery;
use App\Characters\Infrastructure\Api\CharacterResource;
use App\Characters\Infrastructure\Api\Input\SaveCharacterInput;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * POST /api/campaigns/{campaignId}/characters (FR-022..FR-024). Sheet
 * breaches surface as 422 SheetValidationProblem via the exception
 * listener; success returns the freshly created view together with its
 * sheet structure metadata.
 *
 * @implements ProcessorInterface<SaveCharacterInput, CharacterResource>
 */
final readonly class CreateCharacterProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateCharacterHandler $handler,
        private \App\Characters\Application\ListCharactersHandler $projection,
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

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        /** @var array<string, mixed> $attributes */
        $attributes = $data->attributes ?? [];

        $created = $this->handler->handle(new CreateCharacterCommand(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
            $data->kind,
            $data->name,
            $attributes,
        ));

        foreach ($this->projection->handle(new ListCharactersQuery(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
        )) as $view) {
            if ($view->id === $created->id()->toString()) {
                return \App\Characters\Infrastructure\Api\Provider\CharactersProvider::fromData($view);
            }
        }

        throw new \RuntimeException('The created character could not be projected.');
    }
}
