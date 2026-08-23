<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;

/**
 * Full play state projection (contract CampaignState/StageView, FR-014).
 *
 * Stage identity is the stage NAME (US1 decision): the contract's
 * `currentStage.id` and `toStageId` fields carry the stage-name value, with
 * `name` as denormalized display copy.
 */
#[ApiResource(
    shortName: 'CampaignState',
    formats: ['json' => 'application/json'],
    operations: [
        new Post(
            uriTemplate: '/campaigns',
            input: Input\StartCampaignInput::class,
            processor: Processor\StartCampaignProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            validationContext: ['skip_validation_groups' => true],
        ),
        new Get(
            uriTemplate: '/campaigns/{campaignId}',
            provider: Provider\CampaignStateProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
        ),
        new Delete(
            uriTemplate: '/campaigns/{campaignId}',
            provider: Provider\CampaignStateProvider::class,
            processor: Processor\DeleteCampaignProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
        ),
        new Post(
            uriTemplate: '/campaigns/{campaignId}/advance',
            input: Input\AdvanceStageInput::class,
            processor: Processor\AdvanceStageProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
            validationContext: ['skip_validation_groups' => true],
        ),
    ],
)]
final readonly class CampaignStateResource
{
    public function __construct(
        #[ApiProperty(identifier: false)]
        public string $id = '',
        public string $gameSystemId = '',
        public ?StageResource $currentStage = null,
    ) {
    }
}

/**
 * Engine view of the current stage (contract StageView).
 */
final readonly class StageResource
{
    /**
     * @param list<StageActionResource> $suggestedActions
     */
    public function __construct(
        public string $id = '',
        public string $name = '',
        public string $guidance = '',
        public array $suggestedActions = [],
    ) {
    }
}

/**
 * Suggested next action (contract SuggestedAction). `toStageId` mirrors the
 * name-keyed identity decision documented above.
 */
final readonly class StageActionResource
{
    public function __construct(
        public string $kind = 'advance',
        public ?string $toStageId = null,
        public ?string $toStageName = null,
        public string $prompt = '',
    ) {
    }
}
