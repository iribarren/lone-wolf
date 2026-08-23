<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Oracles\Application\Command\SaveConsultationToJournal;
use App\Oracles\Application\ConsultOracleHandler;
use App\Oracles\Application\SaveConsultationToJournalHandler;
use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Infrastructure\Api\ConsultationOutcomeResource;
use App\Oracles\Infrastructure\Api\ConsultedEntryResource;
use App\Oracles\Infrastructure\Api\Input\ConsultOracleInput;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\OracleId;

/**
 * POST /api/campaigns/{campaignId}/oracles/{oracleId}/consult (FR-010):
 * exactly one weighted-random answer, or a friendly empty/unavailable
 * payload — all at HTTP 200. With `save: true` a selected result is
 * persisted to the journal in the same round trip (US4 scenario 3) and the
 * response carries its journalEntryId.
 *
 * @implements ProcessorInterface<ConsultOracleInput, ConsultationOutcomeResource>
 */
final readonly class ConsultOracleProcessor implements ProcessorInterface
{
    public function __construct(
        private ConsultOracleHandler $consultHandler,
        private SaveConsultationToJournalHandler $saveHandler,
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
        \assert($data instanceof ConsultOracleInput);

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));
        $rawOracleId = $uriVariables['oracleId'] ?? '';
        \assert(is_string($rawOracleId));

        $playerId = $this->currentUser->currentUserId();
        $outcome = $this->consultHandler->handle(
            $playerId,
            CampaignId::fromString($rawCampaignId),
            OracleId::fromString($rawOracleId),
        );

        if (!$outcome->isSelected() || $outcome->selected() === null) {
            return new ConsultationOutcomeResource(
                status: $outcome->isEmptyTable() ? 'empty_table' : 'unavailable',
            );
        }

        $entry = $outcome->selected();

        $journalEntryId = null;

        if ($data->save) {
            $oracle = $this->oracles->get(OracleId::fromString($rawOracleId));

            if ($oracle !== null) {
                $saved = $this->saveHandler->handle(new SaveConsultationToJournal(
                    $playerId,
                    CampaignId::fromString($rawCampaignId),
                    $oracle->title(),
                    $entry->text(),
                ));
                $journalEntryId = $saved->id()->toString();
            }
        }

        return new ConsultationOutcomeResource(
            status: 'selected',
            entry: new ConsultedEntryResource($entry->id()->toString(), $entry->text()),
            journalEntryId: $journalEntryId,
        );
    }
}
