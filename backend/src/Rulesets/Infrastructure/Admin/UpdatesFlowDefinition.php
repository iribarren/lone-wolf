<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem;
use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Shared jsonb-payload → command translation for the EasyAdmin CRUDs that
 * edit a game system's campaign flow (Systems create/edit + the dedicated
 * Campaign flows section). Structural validation stays with the Application
 * layer (UpdateFlowDefinitionHandler / FlowFactory); this trait only reads.
 */
trait UpdatesFlowDefinition
{
    private const SUPERSEDED_MESSAGE = 'Your changes were superseded — another edit was saved first. Review the current version below and apply your changes again.';

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private static function stageNames(array $payload): array
    {
        $names = [];
        foreach (is_array($payload['stages'] ?? null) ? $payload['stages'] : [] as $stage) {
            if (is_string($stage)) {
                $names[] = $stage;
            } elseif (is_array($stage) && is_string($stage['name'] ?? null)) {
                $names[] = $stage['name'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{from: string, to: string}>
     */
    private static function transitions(array $payload): array
    {
        /** @var list<array{from: string, to: string}> $transitions */
        $transitions = [];
        foreach (is_array($payload['transitions'] ?? null) ? $payload['transitions'] : [] as $transition) {
            if (is_array($transition)) {
                $transitions[] = [
                    'from' => isset($transition['from']) && is_string($transition['from']) ? $transition['from'] : '',
                    'to' => isset($transition['to']) && is_string($transition['to']) ? $transition['to'] : '',
                ];
            }
        }

        return $transitions;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function startingStage(array $payload): string
    {
        $starting = $payload['starting_stage'] ?? '';

        return is_string($starting) ? $starting : '';
    }

    private static function updateFlowCommand(PersistenceGameSystem $row): UpdateFlowDefinitionCommand
    {
        $payload = $row->flowDefinition();

        return new UpdateFlowDefinitionCommand(
            GameSystemId::fromString($row->id()),
            self::stageNames($payload),
            self::startingStage($payload),
            self::transitions($payload),
        );
    }
}
