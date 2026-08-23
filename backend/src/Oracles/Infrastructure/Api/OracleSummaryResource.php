<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Oracles\Infrastructure\Api\Input\ConsultOracleInput;
use App\Oracles\Infrastructure\Api\Processor\ConsultOracleProcessor;
use App\Oracles\Infrastructure\Api\Provider\OracleListProvider;

/**
 * US4 player-facing oracle surface (contract paths /campaigns/{campaignId}/oracles*).
 *
 * GET lists only the tables applicable to the campaign's system — its own
 * plus global ones (FR-009); POST consult yields exactly one weighted-random
 * result or a friendly notice payload (FR-010/FR-011).
 */
#[ApiResource(
    shortName: 'OracleSummary',
    formats: ['json' => 'application/json'],
    operations: [
        new Get(
            uriTemplate: '/campaigns/{campaignId}/oracles',
            uriVariables: ['campaignId'],
            provider: Provider\OracleListProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
        ),
        new Post(
            uriTemplate: '/campaigns/{campaignId}/oracles/{oracleId}/consult',
            uriVariables: ['campaignId', 'oracleId'],
            input: Input\ConsultOracleInput::class,
            processor: Processor\ConsultOracleProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
            validationContext: ['skip_validation_groups' => true],
            status: 200,
        ),
        new Post(
            uriTemplate: '/campaigns/{campaignId}/oracles/{oracleId}/save',
            uriVariables: ['campaignId', 'oracleId'],
            input: Input\SaveConsultationInput::class,
            processor: Processor\SaveConsultationProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
            validationContext: ['skip_validation_groups' => true],
            status: 201,
        ),
    ],
)]
final readonly class OracleSummaryResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $oracleId = '',
        public string $title = '',
        public string $scopeType = '',
        public int $entryCount = 0,
    ) {
    }
}

/**
 * Contract schema ConsultationOutcome: {status, entry?, journalEntryId?}.
 * All three statuses answer 200 — an empty table or unavailable oracle is a
 * notice, not an error (FR-011 / Edge Cases §3).
 */
final readonly class ConsultationOutcomeResource
{
    public function __construct(
        public string $status = 'selected',
        public ?ConsultedEntryResource $entry = null,
        public ?string $journalEntryId = null,
    ) {
    }
}

/**
 * The consulted row (contract ConsultationOutcome.entry).
 */
final readonly class ConsultedEntryResource
{
    public function __construct(
        public string $entryId = '',
        public string $text = '',
    ) {
    }
}
