<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * Raised when a campaign id does not exist — or exists but belongs to
 * another player. Both cases collapse into "not found" at the API boundary
 * so foreign campaigns are never disclosed (FR-019).
 */
final class CampaignNotFoundException extends \RuntimeException
{
    public static function forCampaign(string $campaignId): self
    {
        return new self(sprintf('Campaign "%s" was not found.', $campaignId));
    }
}
