<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Discriminator between the two scoping strategies (FR-008). Bridges
 * authoring payloads and storage columns to the {@see OracleScope} VOs and
 * refuses inconsistent combinations up front.
 */
enum OracleScopeType: string
{
    case Global = 'global';

    case System = 'system';

    /**
     * @throws \InvalidArgumentException When a global scope carries a system
     *                                   binding or a system scope misses it.
     */
    public function scope(?GameSystemId $systemId): OracleScope
    {
        return match ($this) {
            self::Global => $systemId === null
                ? new GlobalScope()
                : throw new \InvalidArgumentException('A globally scoped oracle must not be bound to a game system.'),
            self::System => $systemId !== null
                ? new SystemScope($systemId)
                : throw new \InvalidArgumentException('A system-scoped oracle requires its game system.'),
        };
    }

    public static function fromScope(OracleScope $scope): self
    {
        return $scope->isGlobal() ? self::Global : self::System;
    }
}
