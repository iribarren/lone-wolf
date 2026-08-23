<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Query;

/**
 * Contract CampaignState — everything a player needs to resume: which stage
 * they occupy plus engine-derived guidance and suggested actions (FR-014).
 */
final readonly class CampaignState
{
    public function __construct(
        public string $campaignId,
        public string $gameSystemId,
        public StageView $currentStage,
    ) {
    }
}
