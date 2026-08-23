<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Api;

/**
 * Paged journal envelope (contract /campaigns/{id}/journal response).
 */
final readonly class JournalPageResource
{
    /**
     * @param list<JournalEntryResource> $entries
     */
    public function __construct(
        public array $entries = [],
        public ?string $nextCursor = null,
    ) {
    }
}
