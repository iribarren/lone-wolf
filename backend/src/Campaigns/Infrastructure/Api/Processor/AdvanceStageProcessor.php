<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Campaigns\Application\Command\AdvanceStageCommand;
use App\Campaigns\Application\AdvanceStageHandler;
use App\Campaigns\Infrastructure\Api\CampaignStateResource;
use App\Campaigns\Infrastructure\Api\Input\AdvanceStageInput;
use App\Campaigns\Infrastructure\Api\Provider\CampaignStateProvider;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * POST /api/campaigns/{campaignId}/advance (FR-016): moves along a legal
 * transition or is refused with a problem+json listing legal alternatives.
 *
 * @implements ProcessorInterface<AdvanceStageInput, CampaignStateResource>
 */
final readonly class AdvanceStageProcessor implements ProcessorInterface
{
    public function __construct(
        private AdvanceStageHandler $handler,
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
        \assert($data instanceof AdvanceStageInput);

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        $state = $this->handler->handle(new AdvanceStageCommand(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
            $data->toStageId,
        ));

        return CampaignStateProvider::fromView($state);
    }
}
