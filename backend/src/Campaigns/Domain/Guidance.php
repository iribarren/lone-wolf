<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * Pacing guidance derived by the {@see FlowEngine} for the stage a campaign
 * currently occupies: a prompt plus the actions the player may take.
 */
final readonly class Guidance
{
    /**
     * @param list<SuggestedAction> $suggestedActions
     */
    public function __construct(
        public string $stageName,
        public string $prompt,
        public array $suggestedActions,
    ) {
    }
}
