<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * One actionable suggestion shown to the player (contract SuggestedAction):
 * either "advance to stage X" or "conclude the campaign" on terminal stages.
 */
final readonly class SuggestedAction
{
    private function __construct(
        public SuggestedActionKind $kind,
        public ?string $toStageName,
        public string $prompt,
    ) {
    }

    public static function advanceTo(string $toStageName): self
    {
        return new self(SuggestedActionKind::Advance, $toStageName, sprintf('Advance to %s', $toStageName));
    }

    public static function conclude(): self
    {
        return new self(SuggestedActionKind::Conclude, null, 'Conclude this story');
    }
}
