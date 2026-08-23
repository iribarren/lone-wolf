<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Persistence;

use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Domain\FlowEdge;
use App\Campaigns\Domain\FlowGraph;
use App\Campaigns\Domain\FlowStageNode;
use App\Shared\Domain\Identifier\GameSystemId;
use Doctrine\DBAL\Connection;

/**
 * Reads the Rulesets-owned game_systems row and translates it into the
 * Campaigns-owned FlowGraph — no Rulesets class is imported (Constitution
 * II; the storage is shared, never the model).
 */
final readonly class DoctrineFlowDefinitionProvider implements FlowDefinitionProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function forSystem(GameSystemId $gameSystemId): ?FlowGraph
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->executeQuery(
            'SELECT status, name, flow_definition FROM game_systems WHERE id = :id',
            ['id' => $gameSystemId->toString()],
        )->fetchAssociative();

        if ($row === false || !is_string($row['status'] ?? null) || !is_string($row['name'] ?? null)) {
            return null;
        }

        $payload = self::decodePayload(is_string($row['flow_definition'] ?? null) ? $row['flow_definition'] : '');

        return new FlowGraph(
            stages: self::stageNodes(self::arrayKey($payload, 'stages')),
            edges: self::edges(self::arrayKey($payload, 'transitions')),
            startingStage: self::stringKey($payload, 'starting_stage'),
            active: $row['status'] === 'active',
            systemName: $row['name'],
        );
    }

    /**
     * @return list<FlowStageNode>
     */
    private static function stageNodes(mixed $rawStages): array
    {
        if (!is_array($rawStages)) {
            throw new \RuntimeException('A stored flow definition has a malformed stages list.');
        }

        $stages = [];

        foreach ($rawStages as $stage) {
            if (!is_array($stage) || !is_string($stage['name'] ?? null)) {
                throw new \RuntimeException('A stored flow definition has a malformed stage.');
            }

            $guidance = $stage['guidance'] ?? '';

            $stages[] = new FlowStageNode($stage['name'], is_string($guidance) ? $guidance : '');
        }

        return $stages;
    }

    /**
     * @return list<FlowEdge>
     */
    private static function edges(mixed $rawEdges): array
    {
        if (!is_array($rawEdges)) {
            throw new \RuntimeException('A stored flow definition has a malformed transitions list.');
        }

        $edges = [];

        foreach ($rawEdges as $edge) {
            if (!is_array($edge) || !is_string($edge['from'] ?? null) || !is_string($edge['to'] ?? null)) {
                throw new \RuntimeException('A stored flow definition has a malformed transition.');
            }

            $edges[] = new FlowEdge($edge['from'], $edge['to']);
        }

        return $edges;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function stringKey(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function arrayKey(array $payload, string $key): mixed
    {
        return $payload[$key] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePayload(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('A stored flow definition could not be decoded.');
        }

        if (!is_array($decoded)) {
            return [];
        }

        $payload = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
