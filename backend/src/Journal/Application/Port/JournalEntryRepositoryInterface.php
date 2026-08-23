<?php

declare(strict_types=1);

namespace App\Journal\Application\Port;

use App\Journal\Application\Query\JournalPage;
use App\Journal\Domain\JournalEntry;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * Append-only journal persistence (Constitution I port). Reads are keyset
 * paginated newest-first so a 500-entry history stays fast (SC-008).
 */
interface JournalEntryRepositoryInterface
{
    public function add(JournalEntry $entry): void;

    /**
     * @param string|null $stageName optional stage filter (FR-017 grouping)
     * @param string|null $cursor    opaque keyset cursor from JournalPage::nextCursor
     * @param int         $limit     page size (1..200)
     */
    public function page(CampaignId $campaignId, ?string $stageName, ?string $cursor, int $limit): JournalPage;

    /** Used by confirmed campaign deletion — journal history is not kept (FR-020). */
    public function deleteAllForCampaign(CampaignId $campaignId): void;
}
