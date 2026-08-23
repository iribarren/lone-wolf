<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Strategy value object answering where an oracle may be seen (FR-008):
 * {@see GlobalScope} is visible to every campaign, {@see SystemScope} only
 * to campaigns of its bound game system (FR-009 predicate).
 */
abstract readonly class OracleScope
{
    abstract public function isGlobal(): bool;

    /**
     * FR-009 visibility predicate: `isGlobal() OR scoped to $systemId`.
     */
    abstract public function isAvailableTo(GameSystemId $systemId): bool;
}
