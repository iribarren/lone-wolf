<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Journal\Application\ListJournalEntriesHandler;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Journal\Infrastructure\Api\JournalEntryResource;
use App\Journal\Infrastructure\Api\JournalPageResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GET /api/campaigns/{campaignId}/journal?stageId=&cursor= (FR-017):
 * newest-first keyset page. Ownership (FR-019) is enforced upstream via the
 * Campaigns play-loop fetcher before the Journal context answers.
 *
 * @implements ProviderInterface<JournalPageResource>
 */
final readonly class JournalPageProvider implements ProviderInterface
{
    public function __construct(
        private ListJournalEntriesHandler $handler,
        private CampaignRepositoryInterface $campaigns,
        private CurrentUserProviderInterface $currentUser,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JournalPageResource
    {
        $request = $this->requestStack->getCurrentRequest();

        $playerId = $this->currentUser->currentUserId();
        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));
        $campaignId = CampaignId::fromString($rawCampaignId);

        // Upstream ownership gate (FR-019) — identical refusal for unknown
        // and foreign campaigns.
        (new OwnedCampaignFetcher($this->campaigns))->fetch($campaignId, $playerId);

        $stageFilter = $request?->query->getString('stageId');
        $cursor = $request?->query->getString('cursor');

        $page = $this->handler->handle(new ListJournalEntriesQuery(
            $playerId,
            $campaignId,
            stageName: $stageFilter === '' ? null : $stageFilter,
            cursor: $cursor === '' ? null : $cursor,
        ));

        return new JournalPageResource(
            array_map(JournalEntryResource::fromDomain(...), $page->entries),
            $page->nextCursor,
        );
    }
}
