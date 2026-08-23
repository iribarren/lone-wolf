<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Scope bound to exactly one game system: only campaigns of that system
 * see the oracle (FR-008).
 */
final readonly class SystemScope extends OracleScope
{
    public function __construct(private GameSystemId $systemId)
    {
    }

    public function systemId(): GameSystemId
    {
        return $this->systemId;
    }

    #[\Override]
    public function isGlobal(): bool
    {
        return false;
    }

    #[\Override]
    public function isAvailableTo(GameSystemId $systemId): bool
    {
        return $this->systemId->equals($systemId);
    }
}
