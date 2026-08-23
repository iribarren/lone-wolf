<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

/**
 * Persistence model for the Campaigns context (see Constitution I note on
 * PersistenceUser). Flat row mirroring the aggregate snapshot — no ORM
 * relations cross bounded-context lines.
 */
#[ORM\Entity]
#[ORM\Table(name: 'campaigns')]
#[ORM\Index(name: 'idx_campaigns_player', columns: ['player_id'])]
class PersistenceCampaign
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $playerId;

    /** Bound exactly once at creation, never re-bound (FR-012). */
    #[ORM\Column(type: 'string', length: 36)]
    private string $gameSystemId;

    #[ORM\Column(type: 'string', length: 120)]
    private string $stageName;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $playerId,
        string $gameSystemId,
        string $stageName,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->playerId = $playerId;
        $this->gameSystemId = $gameSystemId;
        $this->stageName = $stageName;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Applies a domain position change. The system binding is deliberately
     * absent: it cannot change after creation (FR-012).
     */
    public function moveTo(string $stageName, \DateTimeImmutable $at): void
    {
        $this->stageName = $stageName;
        $this->updatedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function playerId(): string
    {
        return $this->playerId;
    }

    public function gameSystemId(): string
    {
        return $this->gameSystemId;
    }

    public function stageName(): string
    {
        return $this->stageName;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
