<?php

declare(strict_types=1);

namespace App\Characters\Application\Query;

/**
 * Contract-facing sheet metadata so the UI renders the form dynamically —
 * no hardcoded fields anywhere (T082).
 */
final readonly class SheetStructureView
{
    /**
     * @param list<SheetFieldView> $fields
     */
    public function __construct(
        public int $version,
        public array $fields,
    ) {
    }
}
