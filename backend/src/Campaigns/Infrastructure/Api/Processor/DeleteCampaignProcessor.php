<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Campaigns\Application\Command\DeleteCampaignCommand;
use App\Campaigns\Application\DeleteCampaignHandler;
use App\Campaigns\Infrastructure\Api\CampaignStateResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * DELETE /api/campaigns/{campaignId}?confirm=true (FR-020): irreversible
 * delete cascading the journal. Without the flag the handler refuses with
 * ConfirmationRequiredException (mapped to 400 by the problem listener).
 *
 * @implements ProcessorInterface<CampaignStateResource, null>
 */
final readonly class DeleteCampaignProcessor implements ProcessorInterface
{
    public function __construct(
        private DeleteCampaignHandler $handler,
        private CurrentUserProviderInterface $currentUser,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $request = $this->requestStack->getCurrentRequest();

        $confirmed = $request !== null && $request->query->getBoolean('confirm');

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        $this->handler->handle(new DeleteCampaignCommand(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
            confirm: $confirmed,
        ));

        return null;
    }
}
