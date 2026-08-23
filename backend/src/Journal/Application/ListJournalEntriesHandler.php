<?php

declare(strict_types=1);

namespace App\Journal\Application;

use App\Journal\Application\Query\JournalPage;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;

/**
 * GET /api/campaigns/{id}/journal (FR-017): chronological, keyset-paginated
 * journal page; entries stay groupable by their stage stamp. Campaign
 * ownership (FR-019) is enforced upstream at the Campaigns API boundary —
 * the Journal context never judges players, only campaigns.
 */
final readonly class ListJournalEntriesHandler
{
    private const CURSOR_CONTEXT = 'lone-wolf.journal';

    public function __construct(private JournalEntryRepositoryInterface $entries)
    {
    }

    public function handle(ListJournalEntriesQuery $query): JournalPage
    {
        return $this->entries->page(
            $query->campaignId,
            $query->stageName,
            $query->cursor === null ? null : self::decode($query->cursor),
            $query->limit,
        );
    }

    /** Wraps a raw cursor into the opaque token handed to clients. */
    public static function encode(string $rawCursor): string
    {
        return base64_encode(self::CURSOR_CONTEXT.'|'.$rawCursor);
    }

    private static function decode(string $cursor): string
    {
        $raw = base64_decode($cursor, true);

        if ($raw === false || !str_starts_with($raw, self::CURSOR_CONTEXT.'|')) {
            throw new \InvalidArgumentException('The journal pagination cursor is not valid.');
        }

        return substr($raw, strlen(self::CURSOR_CONTEXT) + 1);
    }
}
