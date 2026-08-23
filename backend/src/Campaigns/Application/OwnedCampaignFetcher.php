<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Domain\Campaign;
use App\Campaigns\Domain\CampaignAccessDeniedException;
use App\Campaigns\Domain\CampaignNotFoundException;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * FR-019 guard shared by every campaign-scoped operation: unknown ids and
 * foreign campaigns both refuse without disclosing which happened.
 */
final readonly class OwnedCampaignFetcher
{
    public function __construct(private CampaignRepositoryInterface $campaigns)
    {
    }

    public function fetch(CampaignId $campaignId, UserId $requester): Campaign
    {
        $campaign = $this->campaigns->get($campaignId);

        if ($campaign === null) {
            throw CampaignNotFoundException::forCampaign($campaignId->toString());
        }

        if (!$campaign->isOwnedBy($requester)) {
            throw CampaignAccessDeniedException::toCampaign($campaignId->toString());
        }

        return $campaign;
    }
}
