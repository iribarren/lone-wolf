<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Domain\FlowEngine;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * GET /api/campaigns/{id} (FR-014): current stage, pacing guidance and the
 * suggested actions the player may take next.
 */
final readonly class GetCampaignStateHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private FlowDefinitionProviderInterface $flows,
        private FlowEngine $engine,
    ) {
    }

    public function state(CampaignId $campaignId, UserId $requester): Query\CampaignState
    {
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($campaignId, $requester);

        return (new CampaignViews($this->flows, $this->engine))->state($campaign);
    }
}
