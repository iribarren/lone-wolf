<?php

declare(strict_types=1);

namespace App\Oracles\Application\Query;

use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleScopeType;
use App\Shared\Domain\Identifier\GameSystemId;

/**
 * FR-009 read side: the oracles a campaign of the given system may see —
 * every global table plus the system's own.
 */
final class ListOraclesVisibleToSystemQuery
{
    public function __construct(private readonly OracleRepositoryInterface $oracles)
    {
    }

    /** @return list<OracleSummary> */
    public function execute(GameSystemId $systemId): array
    {
        return array_map(
            static fn (Oracle $oracle): OracleSummary => new OracleSummary(
                $oracle->id()->toString(),
                $oracle->title(),
                OracleScopeType::fromScope($oracle->scope())->value,
                $oracle->entryCount(),
            ),
            $this->oracles->visibleTo($systemId),
        );
    }
}
