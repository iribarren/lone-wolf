<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Campaigns\Application\Command\AppendNarrativeEntryCommand;
use App\Campaigns\Application\AppendNarrativeEntryHandler;
use App\Oracles\Application\Command\SaveConsultationToJournal;
use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Application\SaveConsultationToJournalHandler;
use App\Oracles\Infrastructure\Api\ConsultationOutcomeResource;
use App\Oracles\Infrastructure\Api\Input\SaveConsultationInput;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\OracleId;

/**
 * POST /api/campaigns/{campaignId}/oracles/{oracleId}/save — journals the
 * result the player already saw (no re-roll, US4 scenario 3). A non-empty
 * interpretation is appended as a follow-up narrative entry so the player's
 * reading of the omen lives beside it.
 *
 * @implements ProcessorInterface<SaveConsultationInput, ConsultationOutcomeResource>
 */
final readonly class SaveConsultationProcessor implements ProcessorInterface
{
    public function __construct(
        private SaveConsultationToJournalHandler $saveHandler,
        private AppendNarrativeEntryHandler $appendNarrative,
        private OracleRepositoryInterface $oracles,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConsultationOutcomeResource
    {
        \assert($data instanceof SaveConsultationInput);

        if (trim($data->text) === '') {
            throw new \InvalidArgumentException('A saved consultation needs its result text.');
        }

        $playerId = $this->currentUser->currentUserId();

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));
        $rawOracleId = $uriVariables['oracleId'] ?? '';
        \assert(is_string($rawOracleId));

        $campaignId = CampaignId::fromString($rawCampaignId);
        $oracle = $this->oracles->get(OracleId::fromString($rawOracleId));

        $entry = $this->saveHandler->handle(new SaveConsultationToJournal(
            $playerId,
            $campaignId,
            $oracle?->title() ?? 'Unknown oracle',
            $data->text,
        ));

        $interpretation = trim($data->interpretation);

        if ($interpretation !== '') {
            $this->appendNarrative->handle(new AppendNarrativeEntryCommand($playerId, $campaignId, $interpretation));
        }

        return new ConsultationOutcomeResource(
            status: 'selected',
            journalEntryId: $entry->id()->toString(),
        );
    }
}
