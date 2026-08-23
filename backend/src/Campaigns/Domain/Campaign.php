<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;

/**
 * Aggregate root of the Campaigns context (data-model.md).
 *
 * - the player owns the campaign and every read is scoped to them (FR-019);
 * - the game system is bound exactly once at creation, never re-bound
 *   (FR-012) — deactivating the system later leaves running campaigns alone;
 * - advancing mutates only the current position along legal transitions.
 */
final readonly class Campaign
{
    private function __construct(
        private CampaignId $id,
        private UserId $playerId,
        private GameSystemId $gameSystemId,
        private StagePosition $position,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function start(
        CampaignId $id,
        UserId $playerId,
        StagePosition $startingPosition,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $playerId, $startingPosition->gameSystemId, $startingPosition, $now, $now);
    }

    /** @internal reconstitution only */
    public static function reconstitute(
        CampaignId $id,
        UserId $playerId,
        GameSystemId $gameSystemId,
        StagePosition $position,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $playerId, $gameSystemId, $position, $createdAt, $updatedAt);
    }

    public function id(): CampaignId
    {
        return $this->id;
    }

    public function playerId(): UserId
    {
        return $this->playerId;
    }

    public function gameSystemId(): GameSystemId
    {
        return $this->gameSystemId;
    }

    public function position(): StagePosition
    {
        return $this->position;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isOwnedBy(UserId $candidate): bool
    {
        return $this->playerId->equals($candidate);
    }

    /**
     * Moves to a new stage. Callers MUST have validated the transition via
     * {@see FlowEngine::assertCanAdvance()} — this method records the move.
     */
    public function advancedTo(string $stageName, \DateTimeImmutable $at): self
    {
        if ($this->position->isAt($stageName)) {
            throw new \InvalidArgumentException('The campaign already sits on that stage.');
        }

        return new self($this->id, $this->playerId, $this->gameSystemId, $this->position->movedTo($stageName), $this->createdAt, $at);
    }
}
