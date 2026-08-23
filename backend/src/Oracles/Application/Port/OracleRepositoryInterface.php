<?php

declare(strict_types=1);

namespace App\Oracles\Application\Port;

use App\Oracles\Domain\Oracle;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;

/**
 * Aggregates port of the Oracles context (Constitution I).
 *
 * visibleTo() answers the FR-009 predicate at storage level: global rows
 * plus the rows scoped to the given system.
 */
interface OracleRepositoryInterface
{
    public function get(OracleId $id): ?Oracle;

    public function save(Oracle $oracle): void;

    /** @return list<Oracle> */
    public function visibleTo(GameSystemId $systemId): array;
}
