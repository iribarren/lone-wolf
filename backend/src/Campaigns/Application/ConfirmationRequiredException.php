<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

/**
 * FR-020: deleting a campaign is permanent, so the API refuses any call
 * missing the explicit `confirm=true` flag.
 */
final class ConfirmationRequiredException extends \InvalidArgumentException
{
    public static function forCampaign(string $campaignId): self
    {
        return new self(sprintf(
            'Deleting campaign "%s" is irreversible. Repeat the request with ?confirm=true to proceed.',
            $campaignId,
        ));
    }
}
