<?php

declare(strict_types=1);

namespace App\Journal\Application\Query;

use App\Journal\Domain\JournalEntry;

/**
 * One page of the campaign journal, newest first. `nextCursor` feeds the
 * next request; null means the end of history has been reached.
 */
final readonly class JournalPage
{
    /**
     * @param list<JournalEntry> $entries
     */
    public function __construct(
        public array $entries,
        public ?string $nextCursor,
    ) {
    }
}
