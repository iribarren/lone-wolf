<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Port;

use App\Campaigns\Domain\FlowGraph;
use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Answers how a game system's campaign flow looks from inside the Campaigns
 * context. Owning this port keeps Campaigns decoupled from Rulesets types
 * (Constitution II); the adapter translates the Rulesets-owned definition
 * into a {@see FlowGraph}.
 *
 * Returns null when the system does not exist; `FlowGraph::active` is false
 * when the system exists but is deactivated (FR-006/FR-012 interplay).
 */
interface FlowDefinitionProviderInterface
{
    public function forSystem(GameSystemId $gameSystemId): ?FlowGraph;
}
