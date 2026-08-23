<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Query\CampaignSummary;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Domain\FlowEngine;
use App\Shared\Domain\Identifier\UserId;

/**
 * GET /api/campaigns — the requesting player's campaigns only (FR-019).
 */
final readonly class ListCampaignsHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private FlowDefinitionProviderInterface $flows,
        private FlowEngine $engine,
    ) {
    }

    /** @return list<CampaignSummary> */
    public function list(UserId $playerId): array
    {
        return array_map(
            fn (\App\Campaigns\Domain\Campaign $campaign): CampaignSummary => (new CampaignViews($this->flows, $this->engine))->summary($campaign),
            $this->campaigns->ownedBy($playerId),
        );
    }
}
