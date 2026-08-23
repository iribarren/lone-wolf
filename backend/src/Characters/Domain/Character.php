<?php

declare(strict_types=1);

namespace App\Characters\Domain;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\CharacterId;

/**
 * Aggregate root of the Characters context (data-model.md): a PC or NPC of
 * one campaign whose attributes conform — at their last conforming write —
 * to the owning system's sheet structure. Drift is projected on read and
 * never silently alters stored data (FR-025).
 */
final readonly class Character
{
    private const MAX_NAME_LENGTH = 120;

    /**
     * @param list<string> $driftIssues
     */
    private function __construct(
        private CharacterId $id,
        private CampaignId $campaignId,
        private CharacterKind $kind,
        private string $name,
        private AttributesMap $attributes,
        private int $validatedStructureVersion,
        private ReviewStatus $reviewStatus,
        private array $driftIssues,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('A character needs a non-empty name.');
        }

        if (mb_strlen($this->name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(sprintf('A character name must not exceed %d characters.', self::MAX_NAME_LENGTH));
        }

        if ($this->validatedStructureVersion < 1) {
            throw new \InvalidArgumentException('The validated structure version stamp must be positive.');
        }
    }

    /**
     * A conforming write: callers validate first and refuse violations, so
     * a created character always starts clean at the current schema version.
     */
    public static function create(
        CampaignId $campaignId,
        CharacterKind $kind,
        string $name,
        AttributesMap $attributes,
        int $schemaVersion,
    ): self {
        return new self(
            CharacterId::generate(),
            $campaignId,
            $kind,
            trim($name),
            $attributes,
            $schemaVersion,
            ReviewStatus::Clean,
            [],
        );
    }

    /**
     * @param list<string> $driftIssues
     *
     * @internal reconstitution only
     */
    public static function reconstitute(
        CharacterId $id,
        CampaignId $campaignId,
        CharacterKind $kind,
        string $name,
        AttributesMap $attributes,
        int $validatedStructureVersion,
        ReviewStatus $reviewStatus,
        array $driftIssues,
    ): self {
        return new self($id, $campaignId, $kind, $name, $attributes, $validatedStructureVersion, $reviewStatus, $driftIssues);
    }

    /**
     * A conforming edit against the CURRENT structure resets drift state.
     *
     * @param array<string, mixed>|null $attributes
     */
    public function with(
        ?string $name = null,
        ?array $attributes = null,
        ?int $structureVersion = null,
    ): self {
        return new self(
            $this->id,
            $this->campaignId,
            $this->kind,
            trim($name ?? $this->name),
            $attributes === null ? $this->attributes : AttributesMap::fromArray($attributes),
            $structureVersion ?? $this->validatedStructureVersion,
            ReviewStatus::Clean,
            [],
        );
    }

    public function id(): CharacterId
    {
        return $this->id;
    }

    public function campaignId(): CampaignId
    {
        return $this->campaignId;
    }

    public function kind(): CharacterKind
    {
        return $this->kind;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function attributes(): AttributesMap
    {
        return $this->attributes;
    }

    public function validatedStructureVersion(): int
    {
        return $this->validatedStructureVersion;
    }

    public function reviewStatus(): ReviewStatus
    {
        return $this->reviewStatus;
    }

    /** @return list<string> */
    public function driftIssues(): array
    {
        return $this->driftIssues;
    }
}
