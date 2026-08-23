<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Command;

use App\Shared\Domain\Identifier\GameSystemId;

final readonly class SetSystemStatusCommand
{
    public function __construct(
        public GameSystemId $systemId,
        public bool $active,
    ) {
    }
}
