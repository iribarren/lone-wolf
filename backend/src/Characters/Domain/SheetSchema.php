<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * Versioned snapshot of a system's character-sheet shape, keyed by field.
 * The version stamps conforming writes and drives drift detection (FR-025).
 */
final readonly class SheetSchema
{
    /** @var array<string, SheetField> */
    private array $fields;

    /**
     * @param array<string, SheetField>|list<SheetField> $fields
     */
    public function __construct(
        public readonly int $version,
        array $fields,
    ) {
        $indexed = [];

        foreach ($fields as $field) {
            $indexed[$field->key()] = $field;
        }

        $this->fields = $indexed;
    }

    /** @return array<string, SheetField> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    public function field(string $key): ?SheetField
    {
        return $this->fields[$key] ?? null;
    }
}
