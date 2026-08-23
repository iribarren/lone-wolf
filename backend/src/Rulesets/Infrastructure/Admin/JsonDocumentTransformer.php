<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\SheetStructure;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Converts between jsonb arrays and pretty JSON textareas, validating against
 * the matching domain value object on submit.
 *
 * @implements DataTransformerInterface<mixed, string>
 */
final class JsonDocumentTransformer implements DataTransformerInterface
{
    public function __construct(private readonly bool $isSheetStructure)
    {
    }

    /**
     * @param mixed $value jsonb array from storage
     */
    public function transform(mixed $value): string
    {
        return is_array($value)
            ? (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : '';
    }

    /**
     * @param mixed $value submitted JSON text
     * @return array<string, mixed>|null
     */
    public function reverseTransform(mixed $value): ?array
    {
        $raw = is_string($value) ? trim($value) : (is_scalar($value) ? trim((string) $value) : '');

        if ($raw === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransformationFailedException(sprintf('Invalid JSON: %s', $e->getMessage()));
        }

        try {
            if ($this->isSheetStructure) {
                $rawFields = is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];

                $fields = [];
                foreach ($rawFields as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    /** @var array<string, mixed> $field */
                    $fields[] = FieldDefinition::fromArray($field);
                }

                SheetStructure::create($fields);
            }
            // Flow payloads are validated by the occupancy-aware handler.
        } catch (\InvalidArgumentException $e) {
            throw new TransformationFailedException($e->getMessage());
        }

        return $decoded;
    }
}
