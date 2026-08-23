<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Command\AdvanceStageCommand;
use App\Campaigns\Application\Query\CampaignState;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Domain\FlowEngine;
use App\Campaigns\Domain\FlowGraph;
use App\Shared\Domain\ClockInterface;

/**
 * POST /api/campaigns/{id}/advance (FR-016): validates the requested move
 * against the system's flow graph — refusals carry every legal alternative.
 */
final readonly class AdvanceStageHandler
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private FlowDefinitionProviderInterface $flows,
        private FlowEngine $engine,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AdvanceStageCommand $command): CampaignState
    {
        // Refuses unknown ids and foreign players identically (FR-019).
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($command->campaignId, $command->playerId);

        $graph = $this->flows->forSystem($campaign->gameSystemId());
        \assert($graph instanceof FlowGraph);

        // Throws IllegalStageTransitionException carrying the legal
        // alternatives when no edge leads to the requested stage (FR-016).
        $this->engine->assertCanAdvance($graph, $campaign->position(), $command->toStageName);

        $moved = $campaign->advancedTo($command->toStageName, $this->clock->now());
        $this->campaigns->add($moved);

        return (new CampaignViews($this->flows, $this->engine))->state($moved);
    }
}
