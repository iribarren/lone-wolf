<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Command;

/**
 * Creates a game system together with its mandatory flow definition
 * (FR-001/FR-002).
 */
final readonly class CreateGameSystemCommand
{
    /**
     * @param list<string>                                        $stageNames
     * @param list<array{from: string, to: string}>               $transitions
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $stageNames,
        public string $startingStage,
        public array $transitions = [],
    ) {
    }
}
