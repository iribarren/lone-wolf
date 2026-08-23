<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * Graph-driven state machine over a {@see FlowGraph} and the campaign's
 * current {@see StagePosition} — pure functions, no I/O, fully deterministic
 * (data-model.md "Campaigns Context"). Acts, scenes and beats are data:
 * stage names come from the admin-authored flow, never from code.
 */
final readonly class FlowEngine
{
    /** @return list<string> names of stages reachable by one legal transition */
    public function legalNextStages(FlowGraph $graph, StagePosition $position): array
    {
        $targets = [];

        foreach ($graph->edges as $edge) {
            if ($edge->from === $position->stageName
                && $edge->to !== $position->stageName
                && !\in_array($edge->to, $targets, true)
            ) {
                $targets[] = $edge->to;
            }
        }

        return $targets;
    }

    /**
     * FR-016 — throws {@see IllegalStageTransitionException} carrying every
     * legal alternative when the requested move is not an existing edge.
     */
    public function assertCanAdvance(FlowGraph $graph, StagePosition $position, string $targetStageName): void
    {
        if (\in_array($targetStageName, $this->legalNextStages($graph, $position), true)) {
            return;
        }

        throw new IllegalStageTransitionException(
            $position->stageName,
            $targetStageName,
            $this->advanceActions($graph, $position),
        );
    }

    /**
     * US2-5 — pacing guidance for the current position. A terminal stage
     * (no outgoing transitions) yields conclusion guidance instead of
     * advance actions.
     */
    public function guidance(FlowGraph $graph, StagePosition $position): Guidance
    {
        $authored = $this->authoredGuidance($graph, $position->stageName);

        if ($this->isTerminal($graph, $position)) {
            return new Guidance(
                $position->stageName,
                $authored === ''
                    ? 'This stage ends the flow. Bring your story to a close whenever you are ready.'
                    : $authored.' No transition leaves this stage — conclude when it feels right.',
                [SuggestedAction::conclude()],
            );
        }

        $actions = $this->advanceActions($graph, $position);

        return new Guidance(
            $position->stageName,
            $authored === ''
                ? sprintf('You are at "%s". Where does the story go next?', $position->stageName)
                : $authored,
            $actions,
        );
    }

    public function isTerminal(FlowGraph $graph, StagePosition $position): bool
    {
        return $this->legalNextStages($graph, $position) === [];
    }

    /** @return list<SuggestedAction> */
    private function advanceActions(FlowGraph $graph, StagePosition $position): array
    {
        return array_map(SuggestedAction::advanceTo(...), $this->legalNextStages($graph, $position));
    }

    private function authoredGuidance(FlowGraph $graph, string $stageName): string
    {
        foreach ($graph->stages as $stage) {
            if ($stage->name === $stageName) {
                return trim($stage->guidance);
            }
        }

        throw new \InvalidArgumentException(sprintf('Stage "%s" is not part of this flow.', $stageName));
    }
}
