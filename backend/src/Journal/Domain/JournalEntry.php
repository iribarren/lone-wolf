<?php

declare(strict_types=1);

namespace App\Journal\Domain;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\JournalEntryId;

/**
 * Aggregate root of the Journal context: one append-only, immutable entry
 * (data-model.md "Journal Context").
 *
 * Every entry captures the stage it was written against — both the stage
 * identifier and a denormalized display copy that survives later renames
 * (FR-015). Kind-specific payloads are mutually exclusive: a narrative
 * carries text, oracle/roll entries carry exactly their snapshot.
 */
final readonly class JournalEntry
{
    private const MAX_NARRATIVE_LENGTH = 10_000;

    private function __construct(
        private JournalEntryId $id,
        private CampaignId $campaignId,
        private string $stageName,
        private JournalEntryKind $kind,
        private ?string $narrative,
        private ?OracleSnapshot $oracleSnapshot,
        private ?RollSnapshot $rollSnapshot,
        private \DateTimeImmutable $createdAt,
    ) {
        if (trim($this->stageName) === '') {
            throw new \InvalidArgumentException('A journal entry must be stamped with its current stage.');
        }

        $this->assertPayloadMatchesKind();
    }

    public static function writeNarrative(
        CampaignId $campaignId,
        string $stageName,
        string $text,
        \DateTimeImmutable $at,
    ): self {
        return new self(
            JournalEntryId::generate(),
            $campaignId,
            $stageName,
            JournalEntryKind::Narrative,
            self::assertNarrative($text),
            null,
            null,
            $at,
        );
    }

    public static function recordOracleResult(
        CampaignId $campaignId,
        string $stageName,
        OracleSnapshot $snapshot,
        \DateTimeImmutable $at,
    ): self {
        return new self(
            JournalEntryId::generate(),
            $campaignId,
            $stageName,
            JournalEntryKind::OracleResult,
            null,
            $snapshot,
            null,
            $at,
        );
    }

    public static function recordDiceRoll(
        CampaignId $campaignId,
        string $stageName,
        RollSnapshot $snapshot,
        \DateTimeImmutable $at,
    ): self {
        return new self(
            JournalEntryId::generate(),
            $campaignId,
            $stageName,
            JournalEntryKind::DiceRoll,
            null,
            null,
            $snapshot,
            $at,
        );
    }

    /**
     * @internal reconstitution only
     *
     * @param array{oracleTitle: string, resultText: string}|null $oracleSnapshot
     * @param array{notation: string, diceValues: list<int>, modifier: int, total: int}|null $rollSnapshot
     */
    public static function reconstitute(
        JournalEntryId $id,
        CampaignId $campaignId,
        string $stageName,
        JournalEntryKind $kind,
        ?string $narrative,
        ?array $oracleSnapshot,
        ?array $rollSnapshot,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $campaignId,
            $stageName,
            $kind,
            $narrative,
            $oracleSnapshot === null ? null : OracleSnapshot::fromArray($oracleSnapshot),
            $rollSnapshot === null ? null : RollSnapshot::fromArray($rollSnapshot),
            $createdAt,
        );
    }

    public function id(): JournalEntryId
    {
        return $this->id;
    }

    public function campaignId(): CampaignId
    {
        return $this->campaignId;
    }

    /** Stage identifier captured at write time (FR-015). */
    public function stageName(): string
    {
        return $this->stageName;
    }

    public function kind(): JournalEntryKind
    {
        return $this->kind;
    }

    /** @internal narrative entries only */
    public function narrative(): ?string
    {
        return $this->narrative;
    }

    /** @internal oracle_result entries only */
    public function oracleSnapshot(): ?OracleSnapshot
    {
        return $this->oracleSnapshot;
    }

    /** @internal dice_roll entries only */
    public function rollSnapshot(): ?RollSnapshot
    {
        return $this->rollSnapshot;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function assertNarrative(string $text): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('A narrative entry must not be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_NARRATIVE_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'A narrative entry is limited to %s characters.',
                number_format(self::MAX_NARRATIVE_LENGTH, 0, ',', ' '),
            ));
        }

        return $trimmed;
    }

    private function assertPayloadMatchesKind(): void
    {
        $valid = match ($this->kind) {
            JournalEntryKind::Narrative => $this->narrative !== null && $this->oracleSnapshot === null && $this->rollSnapshot === null,
            JournalEntryKind::OracleResult => $this->narrative === null && $this->oracleSnapshot instanceof OracleSnapshot && $this->rollSnapshot === null,
            JournalEntryKind::DiceRoll => $this->narrative === null && $this->oracleSnapshot === null && $this->rollSnapshot instanceof RollSnapshot,
        };

        if (!$valid) {
            throw new \InvalidArgumentException(sprintf('The "%s" entry payload does not match its kind.', $this->kind->value));
        }
    }
}
