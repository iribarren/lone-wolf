<?php

declare(strict_types=1);

namespace App\Oracles\Application\Command;

use App\Oracles\Domain\OracleScopeType;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;

/**
 * Full-replacement authoring edit: retitle, re-scope and reweight/edit the
 * entry set in one save (T060).
 */
final readonly class UpdateOracleCommand
{
    /**
     * @param list<array{text: string, weight: int}> $entries
     */
    public function __construct(
        public OracleId $oracleId,
        public string $title,
        public OracleScopeType $scopeType,
        public ?GameSystemId $systemId,
        public array $entries,
    ) {
    }
}
