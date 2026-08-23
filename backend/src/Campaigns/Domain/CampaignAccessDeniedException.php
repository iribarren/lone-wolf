<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * Raised when someone other than the owning player touches a campaign
 * (FR-019). The API layer renders it exactly like a 404 so foreign campaigns
 * are never disclosed.
 */
final class CampaignAccessDeniedException extends \RuntimeException
{
    public static function toCampaign(string $campaignId): self
    {
        return new self(sprintf('Campaign "%s" was not found.', $campaignId));
    }
}
