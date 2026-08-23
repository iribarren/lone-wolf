<?php

declare(strict_types=1);

namespace App\Characters\Application\Query;

/**
 * Contract CharacterView plus the sheet metadata needed to render it.
 */
final readonly class CharacterData
{
    /**
     * @param array<string, mixed> $attributes
     * @param list<string>         $driftIssues
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $name,
        public array $attributes,
        public int $validatedStructureVersion,
        public string $reviewStatus,
        public array $driftIssues,
        public ?SheetStructureView $structure = null,
    ) {
    }
}
