<?php

declare(strict_types=1);

namespace App\Oracles\Application;

use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\ConsultationOutcome;
use App\Oracles\Domain\WeightedOracleSelector;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConsultOracleHandler
{
    public function __construct(
        private OracleRepositoryInterface $oracleRepository,
        private CampaignRepositoryInterface $campaignRepository,
    ) {
    }

    public function handle(CampaignId $campaignId, OracleId $oracleId): ConsultationOutcome
    {
        $campaign = $this->campaignRepository->get($campaignId);

        if ($campaign === null) {
            return ConsultationOutcome::unavailable('campaign not found');
        }

        $systemId = $campaign->gameSystemId();

        $oracle = $this->oracleRepository->get($oracleId);

        if ($oracle === null) {
            return ConsultationOutcome::unavailable('oracle not found');
        }

        $isAvailable = $oracle->isAvailableTo($systemId);
        if (!$isAvailable) {
            return ConsultationOutcome::unavailable('oracle not visible to this campaign');
        }

        $entries = $oracle->entries();
        $selector = new WeightedOracleSelector($entries);
        return $selector->consult();
    }
}