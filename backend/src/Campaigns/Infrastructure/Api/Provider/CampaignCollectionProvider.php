<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Campaigns\Application\ListCampaignsHandler;
use App\Campaigns\Infrastructure\Api\CampaignSummaryResource;
use App\Shared\Application\CurrentUserProviderInterface;

/**
 * @implements ProviderInterface<CampaignSummaryResource>
 */
final readonly class CampaignCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ListCampaignsHandler $handler,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return list<CampaignSummaryResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        \assert($operation instanceof CollectionOperationInterface);

        return array_map(
            CampaignSummaryResource::fromView(...),
            $this->handler->list($this->currentUser->currentUserId()),
        );
    }
}
