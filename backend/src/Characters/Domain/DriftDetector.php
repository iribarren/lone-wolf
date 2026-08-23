<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * FR-025: judges stored sheet data against the CURRENT structure. Drift is
 * reported, never auto-corrected — characters stay readable and editable
 * with their data untouched until a player re-saves them.
 */
final class DriftDetector
{
    public function __construct(private AttributeValidator $validator = new AttributeValidator())
    {
    }

    /**
     * @return list<string> human-readable issues keyed as "field: message"
     */
    public function driftIssues(CharacterKind $kind, SheetSchema $current, AttributesMap $storedAttributes): array
    {
        if ($storedAttributes->count() === 0 && \count($current->fields()) === 0) {
            return [];
        }

        return array_map(
            static fn (AttributeViolation $violation): string => sprintf('%s: %s', $violation->field, $violation->message),
            $this->validator->validate($storedAttributes->toArray(), $kind, $current),
        );
    }
}
