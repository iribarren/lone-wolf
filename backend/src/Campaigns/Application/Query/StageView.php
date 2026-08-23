<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Query;

/**
 * Contract CampaignState — everything a player needs to resume: the stage
 * they sit on plus engine-derived guidance and suggested actions (FR-014).
 */
final readonly class StageView
{
    /**
     * @param list<SuggestedActionView> $suggestedActions
     */
    public function __construct(
        public string $stageName,
        public string $guidance,
        public array $suggestedActions,
    ) {
    }
}
