<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Persistence;

use App\Rulesets\Application\Port\StageOccupancyCheckerInterface;
use App\Shared\Domain\Identifier\GameSystemId;
use Doctrine\DBAL\Connection;

/**
 * Real FR-005 adapter: answers which flow stages of a system host at least
 * one live campaign. Reads the campaigns table directly — the Campaigns
 * model is never imported, only its storage (Constitution I–II).
 *
 * Replaces the US2-placeholder InMemoryStageOccupancyChecker (T036).
 */
final readonly class DoctrineStageOccupancyChecker implements StageOccupancyCheckerInterface
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function occupiedStages(GameSystemId $systemId): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT stage_name FROM campaigns WHERE game_system_id = :systemId',
            ['systemId' => $systemId->toString()],
        );
    }
}
