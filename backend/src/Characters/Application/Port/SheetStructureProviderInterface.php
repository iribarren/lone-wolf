<?php

declare(strict_types=1);

namespace App\Characters\Application\Port;

use App\Characters\Domain\SheetSchema;
use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Answers how a game system's character sheet looks from inside the
 * Characters context (Constitution II — no Rulesets types cross over).
 * Null when the system exists but defines no structure; unknown systems
 * are indistinguishable from that (the caller refuses writes either way).
 */
interface SheetStructureProviderInterface
{
    public function forSystem(GameSystemId $gameSystemId): ?SheetSchema;
}
