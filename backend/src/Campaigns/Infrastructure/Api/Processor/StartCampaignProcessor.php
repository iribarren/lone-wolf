<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\StartCampaignHandler;
use App\Campaigns\Infrastructure\Api\CampaignStateResource;
use App\Campaigns\Infrastructure\Api\Input\StartCampaignInput;
use App\Campaigns\Infrastructure\Api\Provider\CampaignStateProvider;
use App\Shared\Application\CurrentUserProviderInterface;

/**
 * POST /api/campaigns (FR-012/013): binds the player to an ACTIVE system and
 * lands the campaign on the designated starting stage.
 *
 * @implements ProcessorInterface<StartCampaignInput, CampaignStateResource>
 */
final readonly class StartCampaignProcessor implements ProcessorInterface
{
    public function __construct(
        private StartCampaignHandler $handler,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CampaignStateResource
    {
        \assert($data instanceof StartCampaignInput);

        $state = $this->handler->handle(new StartCampaignCommand(
            $this->currentUser->currentUserId(),
            \App\Shared\Domain\Identifier\GameSystemId::fromString($data->gameSystemId),
        ));

        return CampaignStateProvider::fromView($state);
    }
}
