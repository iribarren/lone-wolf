<?php

declare(strict_types=1);

namespace App\Dice\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dice\Application\Command\RollAndLogToJournal;
use App\Dice\Application\RollAndLogHandler;
use App\Dice\Domain\InvalidDiceNotationException;
use App\Dice\Infrastructure\Api\DiceRollResource;
use App\Dice\Infrastructure\Api\Input\RollDiceInput;
use App\Dice\Infrastructure\Api\LoggedRollResource;
use App\Journal\Infrastructure\Api\JournalEntryResource;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;

/**
 * POST /api/campaigns/{campaignId}/rolls (FR-029): the handler enforces
 * ownership (FR-019) and stamps the entry with the campaign's CURRENT stage.
 * Unknown and foreign campaigns collapse into the same 404 via
 * PlayRefusalExceptionListener — existence is never disclosed.
 *
 * @implements ProcessorInterface<RollDiceInput, LoggedRollResource>
 */
final readonly class RollAndLogProcessor implements ProcessorInterface
{
    public function __construct(
        private RollAndLogHandler $handler,
        private CurrentUserProviderInterface $currentUser,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws InvalidDiceNotationException Refused pre-roll (FR-027); FR-019
     *                                      ownership refusals bubble up from the handler.
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoggedRollResource
    {
        \assert($data instanceof RollDiceInput);

        $rawCampaignId = $uriVariables['campaignId'] ?? '';
        \assert(is_string($rawCampaignId));

        $logged = $this->handler->handle(new RollAndLogToJournal(
            $this->currentUser->currentUserId(),
            CampaignId::fromString($rawCampaignId),
            $data->notation,
        ));

        return new LoggedRollResource(
            DiceRollResource::fromDomain($logged->roll),
            JournalEntryResource::fromDomain($logged->entry),
        );
    }
}
