<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Persistence;

use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\FlowStage;
use App\Rulesets\Domain\FlowTransition;
use App\Rulesets\Domain\GameSystemStatus;
use App\Rulesets\Domain\SheetStructure;

/**
 * Serializes Rulesets value objects to/from their jsonb row representation.
 *
 * @phpstan-type FlowPayload array{stages: list<array{name: string, guidance: string}>, starting_stage: string, transitions: list<array{from: string, to: string}>}
 * @phpstan-type SheetPayload array{version: int, fields: list<array<string, mixed>>}
 */
final class RulesetJsonMapper
{
    /**
     * @return FlowPayload
     */
    public static function flowToPayload(FlowDefinition $flow): array
    {
        return [
            'stages' => array_map(static fn (FlowStage $s): array => $s->toArray(), $flow->stages()),
            'starting_stage' => $flow->startingStage()->name(),
            'transitions' => array_map(static fn (FlowTransition $t): array => $t->toArray(), $flow->transitions()),
        ];
    }

    /**
     * @param FlowPayload $payload
     */
    public static function flowFromPayload(array $payload): FlowDefinition
    {
        return FlowDefinition::create(
            array_map(static fn (array|string $stage): FlowStage => FlowStage::fromArray($stage), $payload['stages'] ?? []),
            (string) ($payload['starting_stage'] ?? ''),
            array_map(
                static fn (array $t): FlowTransition => FlowTransition::fromNames((string) ($t['from'] ?? ''), (string) ($t['to'] ?? '')),
                $payload['transitions'] ?? [],
            ),
        );
    }

    /**
     * @return SheetPayload
     */
    public static function sheetToPayload(SheetStructure $structure): array
    {
        return [
            'version' => $structure->version(),
            'fields' => array_map(static fn (FieldDefinition $f): array => $f->toArray(), $structure->fields()),
        ];
    }

    /**
     * @param SheetPayload $payload
     */
    public static function sheetFromPayload(array $payload): SheetStructure
    {
        $fields = array_map(
            static fn (array $field): FieldDefinition => FieldDefinition::fromArray($field),
            $payload['fields'] ?? [],
        );

        return SheetStructure::reconstitute($fields, (int) ($payload['version'] ?? 1));
    }
}
