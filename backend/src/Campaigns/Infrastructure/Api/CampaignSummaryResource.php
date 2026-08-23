<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use App\Campaigns\Application\Query\CampaignSummary;

/**
 * Owned-campaign list row (contract CampaignSummary, FR-019).
 */
#[ApiResource(
    shortName: 'CampaignSummary',
    formats: ['json' => 'application/json'],
    operations: [
        new GetCollection(
            uriTemplate: '/campaigns',
            provider: Provider\CampaignCollectionProvider::class,
            paginationEnabled: false,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
    ],
)]
final readonly class CampaignSummaryResource
{
    public function __construct(
        #[ApiProperty(identifier: false)]
        public string $id = '',
        public string $gameSystemId = '',
        public string $gameSystemName = '',
        public string $currentStageName = '',
        public string $updatedAt = '',
    ) {
    }

    public static function fromView(CampaignSummary $summary): self
    {
        return new self(
            $summary->id,
            $summary->gameSystemId,
            $summary->gameSystemName,
            $summary->currentStageName,
            $summary->updatedAt->format(\DateTimeInterface::ATOM),
        );
    }
}
