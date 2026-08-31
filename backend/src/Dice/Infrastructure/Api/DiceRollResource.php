<?php

declare(strict_types=1);

namespace App\Dice\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dice\Domain\DiceRoll;
use App\Dice\Infrastructure\Api\Input\RollDiceInput;
use App\Dice\Infrastructure\Api\Processor\RollAndLogProcessor;
use App\Dice\Infrastructure\Api\Processor\RollDiceProcessor;

/**
 * US6 player-facing dice surface (contract /dice/roll + /campaigns/{campaignId}/rolls).
 *
 * POST /dice/roll rolls without logging (200 result | 422 DiceNotationProblem);
 * POST /campaigns/{campaignId}/rolls additionally appends a dice_roll journal
 * entry (FR-029) and answers 201 with {roll, journalEntry}.
 */
#[ApiResource(
    shortName: 'DiceRoll',
    formats: ['json' => 'application/json'],
    operations: [
        new Post(
            uriTemplate: '/dice/roll',
            input: Input\RollDiceInput::class,
            processor: Processor\RollDiceProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            validationContext: ['skip_validation_groups' => true],
            status: 200,
        ),
        new Post(
            uriTemplate: '/campaigns/{campaignId}/rolls',
            uriVariables: ['campaignId'],
            input: Input\RollDiceInput::class,
            processor: Processor\RollAndLogProcessor::class,
            output: LoggedRollResource::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
            validationContext: ['skip_validation_groups' => true],
            status: 201,
        ),
    ],
)]
final readonly class DiceRollResource
{
    /**
     * @param list<int> $diceValues
     */
    public function __construct(
        #[ApiProperty(identifier: false)]
        public string $notation = '',
        public array $diceValues = [],
        public int $modifier = 0,
        public int $total = 0,
    ) {
    }

    public static function fromDomain(DiceRoll $roll): self
    {
        return new self(
            $roll->notation()->toString(),
            $roll->diceValues(),
            $roll->modifier(),
            $roll->total(),
        );
    }
}

/**
 * Contract payload of the logged roll: the result plus the created journal
 * entry, both EMBEDDED (openapi.yaml /campaigns/{campaignId}/rolls answers
 * `{roll: DiceRollResult, journalEntry: JournalEntry}`, FR-029).
 *
 * Both properties are themselves `#[ApiResource]` classes, which API Platform
 * serialises as IRI references by default — that produced a `roll` string the
 * player app could not render at all (audit A5). `readableLink: true` keeps
 * the canonical resources as the single source of truth for both shapes
 * (see the DiceInfrastructure -> JournalInfrastructure note in deptrac.yaml)
 * while writing them into the body.
 */
final readonly class LoggedRollResource
{
    public function __construct(
        #[ApiProperty(readableLink: true)]
        public DiceRollResource $roll,
        #[ApiProperty(readableLink: true)]
        public \App\Journal\Infrastructure\Api\JournalEntryResource $journalEntry,
    ) {
    }
}
