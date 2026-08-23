<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * Write-time sheet conformity (FR-022..FR-024): required sets per kind,
 * type checks, select-option membership, unknown-key refusal. Produces
 * field-level violations — never a partial save.
 */
final class AttributeValidator
{
    /**
     * @param array<string, mixed> $attributes raw payload keyed by field key
     * @return list<AttributeViolation>
     */
    public function validate(array $attributes, CharacterKind $kind, SheetSchema $schema): array
    {
        $violations = [];

        foreach ($schema->fields() as $field) {
            $present = \array_key_exists($field->key(), $attributes);
            $value = $attributes[$field->key()] ?? null;
            $required = match ($kind) {
                CharacterKind::Pc => $field->isRequiredForPc(),
                CharacterKind::Npc => $field->isRequiredForNpc(),
            };

            if (!$present || self::isBlank($value)) {
                if ($required) {
                    $violations[] = new AttributeViolation($field->key(), sprintf('%s is required.', $field->label()));
                }

                continue;
            }

            $violation = self::typeViolation($value, $field);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        // Keys the sheet does not know are refused, not silently dropped.
        foreach ($attributes as $key => $_) {
            if (!$schema->has((string) $key)) {
                $violations[] = new AttributeViolation((string) $key, 'Unknown sheet attribute.');
            }
        }

        return $violations;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function typeViolation(mixed $value, SheetField $field): ?AttributeViolation
    {
        switch ($field->type()) {
            case SheetField::TYPE_NUMBER:
                // Booleans are integers in C terms — refuse them explicitly.
                if (!is_int($value) && !is_float($value)) {
                    return new AttributeViolation($field->key(), sprintf('%s must be a number.', $field->label()));
                }

                return null;

            case SheetField::TYPE_SELECT:
                if (!is_string($value) || !in_array($value, $field->options(), true)) {
                    return new AttributeViolation(
                        $field->key(),
                        sprintf('%s must be one of: %s.', $field->label(), implode(', ', $field->options())),
                    );
                }

                return null;

            default:
                if (!is_string($value)) {
                    return new AttributeViolation($field->key(), sprintf('%s must be text.', $field->label()));
                }

                return null;
        }
    }
}
