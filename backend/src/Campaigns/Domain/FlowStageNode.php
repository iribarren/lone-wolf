<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * One stage of a campaign flow as seen by the Campaigns context: its name
 * (identity within the flow) and the admin-authored pacing guidance.
 */
final readonly class FlowStageNode
{
    public function __construct(
        public string $name,
        public string $guidance,
    ) {
    }
}
