<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * A directed legal transition between two flow stages.
 */
final readonly class FlowEdge
{
    public function __construct(
        public string $from,
        public string $to,
    ) {
    }
}
