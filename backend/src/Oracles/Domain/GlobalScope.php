<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * System-agnostic scope: the oracle is available to every campaign
 * regardless of its game system (FR-008).
 */
final readonly class GlobalScope extends OracleScope
{
    #[\Override]
    public function isGlobal(): bool
    {
        return true;
    }

    #[\Override]
    public function isAvailableTo(GameSystemId $systemId): bool
    {
        return true;
    }
}
