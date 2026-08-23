<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Persistence;

use App\Journal\Domain\JournalEntryKind;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persistence model of the append-only journal (Journal context). Snapshots
 * live in jsonb columns; the covering index on (campaign_id, created_at
 * DESC) plus the campaign FK cascade are created by migration (SC-008,
 * FR-020).
 */
#[ORM\Entity]
#[ORM\Table(name: 'journal_entries')]
class PersistenceJournalEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $campaignId;

    /** Denormalized stage snapshot captured at write time (FR-015). */
    #[ORM\Column(type: 'string', length: 120)]
    private string $stageName;

    #[ORM\Column(type: 'string', length: 16, enumType: JournalEntryKind::class)]
    private JournalEntryKind $kind;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $narrative = null;

    /**
     * @var array{oracleTitle: string, resultText: string}|null
     */
    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $oracleSnapshot = null;

    /**
     * @var array{notation: string, diceValues: list<int>, modifier: int, total: int}|null
     */
    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $rollSnapshot = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array{oracleTitle: string, resultText: string}|null      $oracleSnapshot
     * @param array{notation: string, diceValues: list<int>, modifier: int, total: int}|null $rollSnapshot
     */
    public function __construct(
        string $id,
        string $campaignId,
        string $stageName,
        JournalEntryKind $kind,
        ?string $narrative,
        ?array $oracleSnapshot,
        ?array $rollSnapshot,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->campaignId = $campaignId;
        $this->stageName = $stageName;
        $this->kind = $kind;
        $this->narrative = $narrative;
        $this->oracleSnapshot = $oracleSnapshot;
        $this->rollSnapshot = $rollSnapshot;
        $this->createdAt = $createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function campaignId(): string
    {
        return $this->campaignId;
    }

    public function stageName(): string
    {
        return $this->stageName;
    }

    public function kind(): JournalEntryKind
    {
        return $this->kind;
    }

    public function narrative(): ?string
    {
        return $this->narrative;
    }

    /** @return array{oracleTitle: string, resultText: string}|null */
    public function oracleSnapshot(): ?array
    {
        return $this->oracleSnapshot;
    }

    /** @return array{notation: string, diceValues: list<int>, modifier: int, total: int}|null */
    public function rollSnapshot(): ?array
    {
        return $this->rollSnapshot;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
