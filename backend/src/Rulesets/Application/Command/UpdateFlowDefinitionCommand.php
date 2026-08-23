<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Command;

use App\Shared\Domain\Identifier\GameSystemId;

final readonly class UpdateFlowDefinitionCommand
{
    /**
     * @param list<string>                          $stageNames
     * @param list<array{from: string, to: string}> $transitions
     */
    public function __construct(
        public GameSystemId $systemId,
        public array $stageNames,
        public string $startingStage,
        public array $transitions = [],
    ) {
    }
}
