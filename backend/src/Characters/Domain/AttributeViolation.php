<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * One field-level sheet breach (FR-023): messages are keyed by attribute
 * key so the UI can point at the exact offending input.
 */
final readonly class AttributeViolation
{
    public function __construct(
        public string $field,
        public string $message,
    ) {
    }
}
