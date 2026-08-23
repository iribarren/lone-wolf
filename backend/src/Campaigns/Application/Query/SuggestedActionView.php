<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Query;

/**
 * One suggested action of a stage view — contract SuggestedAction.
 * `kind` is the wire representation: "advance" or "conclude".
 */
final readonly class SuggestedActionView
{
    public function __construct(
        public string $kind,
        public ?string $toStageName,
        public string $prompt,
    ) {
    }
}
