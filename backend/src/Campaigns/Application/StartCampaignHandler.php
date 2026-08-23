<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\Query\CampaignState;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Domain\Campaign;
use App\Campaigns\Domain\FlowEngine;
use App\Campaigns\Domain\StagePosition;
use App\Shared\Domain\ClockInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * POST /api/campaigns (FR-012/FR-013): binds the player to an ACTIVE system
 * and lands the campaign on the designated starting stage with its opening
 * guidance. The system id is immutable from here on.
 */
final readonly class StartCampaignHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private FlowDefinitionProviderInterface $flows,
        private FlowEngine $engine,
        private ClockInterface $clock,
    ) {
    }

    public function handle(StartCampaignCommand $command): CampaignState
    {
        $graph = $this->flows->forSystem($command->gameSystemId);

        if ($graph === null) {
            throw SystemNotPlayableException::unknown($command->gameSystemId);
        }

        if (!$graph->active) {
            throw SystemNotPlayableException::inactive($graph->systemName);
        }

        $campaign = Campaign::start(
            CampaignId::generate(),
            $command->playerId,
            new StagePosition($command->gameSystemId, $graph->startingStage),
            $this->clock->now(),
        );

        $this->campaigns->add($campaign);

        return $this->views()->state($campaign);
    }

    private function views(): CampaignViews
    {
        return new CampaignViews($this->flows, $this->engine);
    }
}
