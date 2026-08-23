<?php

declare(strict_types=1);

namespace App\Journal\Application\Query;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * GET /api/campaigns/{id}/journal — newest-first page, optionally filtered
 * to one stage (FR-017), walked with the returned keyset cursor.
 */
final readonly class ListJournalEntriesQuery
{
    public const MAX_LIMIT = 200;

    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public ?string $stageName = null,
        public ?string $cursor = null,
        public int $limit = 50,
    ) {
        if ($this->limit < 1 || $this->limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(sprintf('Journal page size must be between 1 and %d.', self::MAX_LIMIT));
        }
    }
}
