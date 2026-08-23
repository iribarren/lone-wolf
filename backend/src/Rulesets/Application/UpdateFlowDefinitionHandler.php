<?php

declare(strict_types=1);

namespace App\Rulesets\Application;

use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Application\Port\StageOccupancyCheckerInterface;

/**
 * FR-005: refuses any flow modification that would orphan a stage currently
 * occupied by an existing campaign.
 */
final class UpdateFlowDefinitionHandler
{
    public function __construct(
        private readonly RulesetRepositoryInterface $systems,
        private readonly StageOccupancyCheckerInterface $occupancy,
    ) {
    }

    public function handle(UpdateFlowDefinitionCommand $command): void
    {
        $system = $this->systems->get($command->systemId);

        if ($system === null) {
            throw new \InvalidArgumentException('Game system not found.');
        }

        $newFlow = FlowFactory::fromPayload($command->stageNames, $command->startingStage, $command->transitions);
        $newNames = array_map(static fn ($stage) => $stage->name(), $newFlow->stages());

        $orphaned = array_values(array_filter(
            $this->occupancy->occupiedStages($system->id()),
            static fn (string $occupied): bool => !in_array($occupied, $newNames, true),
        ));

        if ($orphaned !== []) {
            throw new \DomainException(sprintf(
                'Cannot modify flow: stage(s) %s are still occupied by existing campaigns. Remove or rename them once no campaign sits there.',
                implode(', ', array_map(static fn (string $s): string => sprintf('"%s"', $s), $orphaned)),
            ));
        }

        $this->systems->save($system->withFlowDefinition($newFlow));
    }
}
