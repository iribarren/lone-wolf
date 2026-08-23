<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Campaigns\Application\Query\CampaignState;
use App\Campaigns\Application\Query\SuggestedActionView;
use App\Campaigns\Application\Query\StageView;
use App\Campaigns\Application\GetCampaignStateHandler;
use App\Campaigns\Infrastructure\Api\CampaignStateResource;
use App\Campaigns\Infrastructure\Api\StageActionResource;
use App\Campaigns\Infrastructure\Api\StageResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * GET /api/campaigns/{campaignId} — full play state (FR-014). Ownership
 * (FR-019) is enforced by the handler: foreign ids read as unknown.
 *
 * @implements ProviderInterface<CampaignStateResource>
 */
final readonly class CampaignStateProvider implements ProviderInterface
{
    public function __construct(
        private GetCampaignStateHandler $handler,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CampaignStateResource
    {
        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));
        $campaignId = CampaignId::fromString($rawCampaignId);

        return self::fromView($this->handler->state($campaignId, $this->currentUser->currentUserId()));
    }

    public static function fromView(CampaignState $state): CampaignStateResource
    {
        return new CampaignStateResource(
            $state->campaignId,
            $state->gameSystemId,
            self::stage($state),
        );
    }

    private static function stage(CampaignState $state): StageResource
    {
        $view = $state->currentStage;

        return new StageResource(
            $view->stageName,
            $view->stageName,
            $view->guidance,
            array_map(self::action(...), $view->suggestedActions),
        );
    }

    private static function action(SuggestedActionView $action): StageActionResource
    {
        if ($action->kind === 'conclude') {
            return new StageActionResource('conclude', null, null, $action->prompt);
        }

        \assert(is_string($action->toStageName));

        return new StageActionResource('advance', $action->toStageName, $action->toStageName, $action->prompt);
    }
}
