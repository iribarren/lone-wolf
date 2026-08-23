<?php

declare(strict_types=1);

namespace App\Rulesets\Application;

use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\FlowTransition;

/**
 * Builds FlowDefinition value objects from command payloads.
 */
final class FlowFactory
{
    /**
     * @param list<string>                          $stageNames
     * @param list<array{from: string, to: string}> $transitions
     */
    public static function fromPayload(array $stageNames, string $startingStage, array $transitions): FlowDefinition
    {
        return FlowDefinition::create(
            $stageNames,
            $startingStage,
            array_map(static fn (array $t): FlowTransition => FlowTransition::fromArray($t), $transitions),
        );
    }
}
