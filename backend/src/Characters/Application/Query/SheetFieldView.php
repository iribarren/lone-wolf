<?php

declare(strict_types=1);

namespace App\Characters\Application\Query;

/**
 * One rendered field of the sheet structure (contract FieldDefinition).
 */
final readonly class SheetFieldView
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $requiredForPc,
        public bool $requiredForNpc,
        public array $options = [],
    ) {
    }
}
