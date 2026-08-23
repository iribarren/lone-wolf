<?php

declare(strict_types=1);

namespace App\Oracles\Application\Command;

use App\Oracles\Domain\OracleScopeType;
use App\Shared\Domain\Identifier\GameSystemId;

final readonly class CreateOracleCommand
{
    /**
     * @param list<array{text: string, weight: int}> $entries
     */
    public function __construct(
        public string $title,
        public OracleScopeType $scopeType,
        public ?GameSystemId $systemId = null,
        public array $entries = [],
    ) {
    }
}
