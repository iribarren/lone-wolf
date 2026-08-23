<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Query;

/** Contract CampaignSummary — list row of the player's campaigns. */
final readonly class CampaignSummary
{
    public function __construct(
        public string $id,
        public string $gameSystemId,
        public string $gameSystemName,
        public string $currentStageName,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
