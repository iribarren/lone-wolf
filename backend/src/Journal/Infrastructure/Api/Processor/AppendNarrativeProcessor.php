<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Campaigns\Application\Command\AppendNarrativeEntryCommand;
use App\Campaigns\Application\AppendNarrativeEntryHandler;
use App\Journal\Infrastructure\Api\Input\AppendNarrativeInput;
use App\Journal\Infrastructure\Api\JournalEntryResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * POST /api/campaigns/{campaignId}/journal (FR-015): the handler stamps the
 * entry with the campaign's current stage and enforces ownership (FR-019).
 *
 * @implements ProcessorInterface<AppendNarrativeInput, JournalEntryResource>
 */
final readonly class AppendNarrativeProcessor implements ProcessorInterface
{
    public function __construct(
        private AppendNarrativeEntryHandler $handler,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JournalEntryResource
    {
        \assert($data instanceof AppendNarrativeInput);

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        $entry = $this->handler->handle(new AppendNarrativeEntryCommand(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
            $data->narrative,
        ));

        return JournalEntryResource::fromDomain($entry);
    }
}
