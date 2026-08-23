<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Query;

use App\Rulesets\Application\Port\RulesetRepositoryInterface;

/**
 * Active-only listing shown to players when starting a campaign (FR-006).
 */
final class ListAvailableSystemsQuery
{
    public function __construct(private readonly RulesetRepositoryInterface $systems)
    {
    }

    /** @return list<SystemSummary> */
    public function execute(): array
    {
        $summaries = [];

        foreach ($this->systems->all() as $system) {
            if (!$system->isActive()) {
                continue;
            }

            $startingStage = $system->flowDefinition()->startingStage();

            $summaries[] = new SystemSummary(
                $system->id()->toString(),
                $system->name(),
                $system->description(),
                $startingStage->name(),
                $startingStage->guidance(),
            );
        }

        usort($summaries, static fn (SystemSummary $a, SystemSummary $b): int => strcmp($a->name, $b->name));

        return $summaries;
    }
}
