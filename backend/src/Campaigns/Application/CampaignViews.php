<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Application\Query\CampaignState;
use App\Campaigns\Application\Query\CampaignSummary;
use App\Campaigns\Application\Query\SuggestedActionView;
use App\Campaigns\Application\Query\StageView;
use App\Campaigns\Domain\Campaign;
use App\Campaigns\Domain\FlowEngine;
use App\Campaigns\Domain\FlowGraph;
use App\Campaigns\Domain\Guidance;
use App\Campaigns\Domain\SuggestedActionKind;

/**
 * Assembles contract-facing read views from aggregates + flow graphs.
 * Kept in one place so Start/Advance/Get/List render identically.
 */
final readonly class CampaignViews
{
    public function __construct(
        private FlowDefinitionProviderInterface $flows,
        private FlowEngine $engine,
    ) {
    }

    public function state(Campaign $campaign): CampaignState
    {
        $guidance = $this->engine->guidance($this->graphFor($campaign), $campaign->position());

        return new CampaignState(
            $campaign->id()->toString(),
            $campaign->gameSystemId()->toString(),
            self::stage($guidance),
        );
    }

    public function summary(Campaign $campaign): CampaignSummary
    {
        return new CampaignSummary(
            $campaign->id()->toString(),
            $campaign->gameSystemId()->toString(),
            $this->graphFor($campaign)->systemName,
            $campaign->position()->stageName,
            $campaign->updatedAt(),
        );
    }

    private static function stage(Guidance $guidance): StageView
    {
        return new StageView(
            $guidance->stageName,
            $guidance->prompt,
            array_map(
                static fn ($action): SuggestedActionView => new SuggestedActionView(
                    match ($action->kind) {
                        SuggestedActionKind::Advance => 'advance',
                        SuggestedActionKind::Conclude => 'conclude',
                    },
                    $action->toStageName,
                    $action->prompt,
                ),
                $guidance->suggestedActions,
            ),
        );
    }

    private function graphFor(Campaign $campaign): FlowGraph
    {
        $graph = $this->flows->forSystem($campaign->gameSystemId());

        \assert($graph instanceof FlowGraph);

        return $graph;
    }
}
