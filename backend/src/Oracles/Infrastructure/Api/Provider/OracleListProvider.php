<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Oracles\Application\Query\ListOraclesVisibleToSystemQuery;
use App\Oracles\Infrastructure\Api\OracleSummaryResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * GET /api/campaigns/{campaignId}/oracles (FR-009): only the tables
 * applicable to the campaign's bound system — global plus its own.
 * Ownership is enforced upstream by the CAMPAIGN_OWNER security expression.
 *
 * @implements ProviderInterface<OracleSummaryResource>
 */
final readonly class OracleListProvider implements ProviderInterface
{
    public function __construct(
        private ListOraclesVisibleToSystemQuery $query,
        private CampaignRepositoryInterface $campaigns,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return list<OracleSummaryResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Touch the current user so unauthenticated access never reaches here
        // even if the security expression is bypassed in tests.
        $this->currentUser->currentUserId();

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        $campaign = $this->campaigns->get(CampaignId::fromString($rawCampaignId));

        if ($campaign === null) {
            return [];
        }

        return array_map(
            static fn ($summary): OracleSummaryResource => new OracleSummaryResource(
                $summary->oracleId,
                $summary->title,
                $summary->scopeType,
                $summary->entryCount,
            ),
            $this->query->execute($campaign->gameSystemId()),
        );
    }
}
