<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Api;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\ApiResource;

/**
 * Active-only system summaries shown to players (contract SystemSummary,
 * FR-006). Read-only projection of the Rulesets Application read query.
 */
#[ApiResource(
    shortName: 'System',
    operations: [
        new GetCollection(
            uriTemplate: '/systems',
            provider: Provider\SystemSummaryProvider::class,
            paginationEnabled: false,
        ),
    ],
)]
final readonly class SystemResource
{
    public function __construct(
        public string $systemId = '',
        public string $name = '',
        public string $description = '',
        public string $startingStage = '',
        public string $openingGuidance = '',
    ) {
    }
}
