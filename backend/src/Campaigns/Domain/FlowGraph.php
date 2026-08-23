<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * Context-owned projection of a game system's campaign flow (the Campaigns
 * context never imports Rulesets types — Constitution II). The adapter of
 * {@see \App\Campaigns\Application\Port\FlowDefinitionProviderInterface}
 * builds it from the admin-authored definition.
 */
final readonly class FlowGraph
{
    /**
     * @param list<FlowStageNode> $stages
     * @param list<FlowEdge>      $edges
     */
    public function __construct(
        public array $stages,
        public array $edges,
        public string $startingStage,
        public bool $active,
        public string $systemName,
    ) {
        foreach ($this->stages as $stage) {
            if ($stage->name === $this->startingStage) {
                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('Starting stage "%s" is not part of the flow.', $this->startingStage));
    }
}
