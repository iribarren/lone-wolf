<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use App\Journal\Domain\OracleSnapshot;
use App\Journal\Domain\RollSnapshot;

/**
 * Append-only journal projection (contract JournalEntry, FR-015/017).
 *
 * Lives on the Campaigns API surface because the play loop owns it: stage
 * ownership (FR-019) is enforced upstream in the same boundary. `stageId`
 * carries the name-keyed identity decision (see CampaignStateResource).
 */
#[ApiResource(
    shortName: 'JournalEntry',
    formats: ['json' => 'application/json'],
    operations: [
        new Get(
            uriTemplate: '/campaigns/{campaignId}/journal',
            provider: Provider\JournalPageProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(
            uriTemplate: '/campaigns/{campaignId}/journal',
            input: Input\AppendNarrativeInput::class,
            processor: Processor\AppendNarrativeProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            validationContext: ['skip_validation_groups' => true],
        ),
    ],
)]
final readonly class JournalEntryResource
{
    /**
     * @param array{oracleTitle: string, resultText: string}|null                              $oracleSnapshot
     * @param array{notation: string, diceValues: list<int>, modifier: int, total: int}|null  $rollSnapshot
     */
    public function __construct(
        #[ApiProperty(identifier: false)]
        public string $id = '',
        public string $stageId = '',
        public string $stageName = '',
        public string $kind = '',
        public ?string $narrative = null,
        public ?array $oracleSnapshot = null,
        public ?array $rollSnapshot = null,
        public string $createdAt = '',
    ) {
    }

    public static function fromDomain(\App\Journal\Domain\JournalEntry $entry): self
    {
        $oracle = $entry->oracleSnapshot();
        $roll = $entry->rollSnapshot();

        return new self(
            $entry->id()->toString(),
            $entry->stageName(),
            $entry->stageName(),
            $entry->kind()->value,
            $entry->narrative(),
            $oracle === null ? null : self::oracleView($oracle),
            $roll === null ? null : self::rollView($roll),
            $entry->createdAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array{oracleTitle: string, resultText: string} */
    public static function oracleView(OracleSnapshot $snapshot): array
    {
        return [
            'oracleTitle' => $snapshot->oracleTitle,
            'resultText' => $snapshot->resultText,
        ];
    }

    /** @return array{notation: string, diceValues: list<int>, modifier: int, total: int} */
    public static function rollView(RollSnapshot $snapshot): array
    {
        return [
            'notation' => $snapshot->notation,
            'diceValues' => $snapshot->diceValues,
            'modifier' => $snapshot->modifier,
            'total' => $snapshot->total,
        ];
    }
}
