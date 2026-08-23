<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

/**
 * Immutable character-sheet structure owned by a game system (FR-022).
 * Carries a monotonically increasing version stamp so drifted characters can
 * be flagged (FR-025) without ever being silently altered.
 */
final readonly class SheetStructure
{
    /** @var array<string, FieldDefinition> */
    private array $fields;

    /**
     * @param list<FieldDefinition> $fields
     */
    private function __construct(array $fields, private int $version)
    {
        $indexed = [];
        foreach ($fields as $field) {
            if (isset($indexed[$field->key()])) {
                throw new \InvalidArgumentException(sprintf('Field keys must be unique ("%s" duplicated).', $field->key()));
            }

            if ($field->key() === '' || trim($field->key()) === '') {
                throw new \InvalidArgumentException('Field keys must be non-empty.');
            }

            if (trim($field->label()) === '') {
                throw new \InvalidArgumentException(sprintf('Field "%s" requires a non-empty label.', $field->key()));
            }

            $indexed[$field->key()] = $field;
        }

        $this->fields = $indexed;
    }

    /**
     * @param list<FieldDefinition> $fields
     */
    public static function create(array $fields): self
    {
        return new self($fields, 1);
    }

    /**
     * Returns a NEW structure carrying the given fields and a bumped version
     * stamp (UpdateSheetStructure handler relies on this bump).
     *
     * @param list<FieldDefinition> $fields
     */
    public function withFields(array $fields): self
    {
        return new self($fields, $this->version + 1);
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return list<FieldDefinition> */
    public function fields(): array
    {
        return array_values($this->fields);
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    public function field(string $key): ?FieldDefinition
    {
        return $this->fields[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->fields);
    }

    /** @return list<string> */
    public function requiredKeysForPc(): array
    {
        return $this->requiredKeys(static fn (FieldDefinition $f): bool => $f->isRequiredForPc());
    }

    /** @return list<string> */
    public function requiredKeysForNpc(): array
    {
        return $this->requiredKeys(static fn (FieldDefinition $f): bool => $f->isRequiredForNpc());
    }

    /**
     * Validates submitted attributes against the structure, returning
     * field-level guidance messages (FR-023). Empty list means conforming.
     *
     * @param array<string, mixed> $attributes
     * @return list<string>
     */
    public function validate(array $attributes, bool $isPc): array
    {
        $errors = [];

        foreach ($this->fields as $key => $field) {
            $present = \array_key_exists($key, $attributes);
            $required = $isPc ? $field->isRequiredForPc() : $field->isRequiredForNpc();

            if ($required && (!$present || $attributes[$key] === null || $attributes[$key] === '')) {
                $errors[] = sprintf('"%s" (%s) is required.', $key, $field->label());

                continue;
            }

            if (!$present || $attributes[$key] === null || $attributes[$key] === '') {
                continue;
            }

            $value = $attributes[$key];

            if ($field->type() === FieldDefinition::TYPE_NUMBER && (!is_scalar($value) || !is_numeric((string) $value))) {
                $errors[] = sprintf('"%s" (%s) must be a number.', $key, $field->label());
            }

            if ($field->type() === FieldDefinition::TYPE_SELECT && is_scalar($value) && !\in_array((string) $value, $field->options(), true)) {
                $errors[] = sprintf(
                    '"%s" (%s) must be one of: %s.',
                    $key,
                    $field->label(),
                    implode(', ', $field->options()),
                );
            }
        }

        return $errors;
    }

    /**
     * @param callable(FieldDefinition):bool $predicate
     * @return list<string>
     */
    private function requiredKeys(callable $predicate): array
    {
        $keys = [];
        foreach ($this->fields as $key => $field) {
            if ($predicate($field)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
