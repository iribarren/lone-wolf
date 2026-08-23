<?php

declare(strict_types=1);

namespace App\Oracles\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\ConsultationOutcome;
use App\Oracles\Domain\WeightedOracleSelector;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\OracleId;
use App\Shared\Domain\Identifier\UserId;

/**
 * US4 scenario 2 (FR-010): consult an oracle for exactly one
 * weighted-random answer, or degrade gracefully — a foreign system's table,
 * a retired table or an unknown campaign yields `unavailable`/denial, never
 * an error (Edge Cases §3). Ownership (FR-019) is enforced through the
 * shared owned-campaign fetcher.
 */
final readonly class ConsultOracleHandler
{
    public function __construct(
        private OracleRepositoryInterface $oracles,
        private CampaignRepositoryInterface $campaigns,
    ) {
    }

    public function handle(UserId $playerId, CampaignId $campaignId, OracleId $oracleId): ConsultationOutcome
    {
        // Refuses unknown ids and foreign players identically (FR-019).
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($campaignId, $playerId);

        $oracle = $this->oracles->get($oracleId);

        if ($oracle === null) {
            return ConsultationOutcome::unavailable('This oracle is no longer available.');
        }

        if (!$oracle->isAvailableTo($campaign->gameSystemId())) {
            return ConsultationOutcome::unavailable('This oracle is not available to this campaign.');
        }

        return (new WeightedOracleSelector($oracle->entries()))->consult();
    }
}
