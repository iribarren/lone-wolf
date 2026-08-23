<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

/**
 * Persistence model of the Characters context. Attributes live in a jsonb
 * column; the campaign FK cascades on confirmed campaign deletion (T080,
 * FR-020 parity with the journal).
 */
#[ORM\Entity]
#[ORM\Table(name: 'characters')]
#[ORM\Index(name: 'idx_characters_campaign', columns: ['campaign_id'])]
class PersistenceCharacter
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    /** Storage-level cascade: deleting the campaign deletes its cast. */
    #[ORM\Column(type: 'string', length: 36)]
    private string $campaignId;

    #[ORM\Column(type: 'string', length: 8)]
    private string $kind;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'jsonb')]
    private array $attributes = [];

    #[ORM\Column(name: 'validated_structure_version', type: 'integer')]
    private int $validatedStructureVersion;

    #[ORM\Column(name: 'review_status', type: 'string', length: 24)]
    private string $reviewStatus = 'clean';

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'drift_issues', type: 'jsonb')]
    private array $driftIssues = [];

    /**
     * @param array<string, mixed> $attributes
     * @param list<string>         $driftIssues
     */
    public function __construct(
        string $id,
        string $campaignId,
        string $kind,
        string $name,
        array $attributes,
        int $validatedStructureVersion,
        string $reviewStatus,
        array $driftIssues = [],
    ) {
        $this->id = $id;
        $this->campaignId = $campaignId;
        $this->kind = $kind;
        $this->replace($name, $attributes, $validatedStructureVersion, $reviewStatus, $driftIssues);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string>         $driftIssues
     */
    public function replace(
        string $name,
        array $attributes,
        int $validatedStructureVersion,
        string $reviewStatus,
        array $driftIssues = [],
    ): void {
        $this->name = $name;
        $this->attributes = $attributes;
        $this->validatedStructureVersion = $validatedStructureVersion;
        $this->reviewStatus = $reviewStatus;
        $this->driftIssues = $driftIssues;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function campaignId(): string
    {
        return $this->campaignId;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function validatedStructureVersion(): int
    {
        return $this->validatedStructureVersion;
    }

    public function reviewStatus(): string
    {
        return $this->reviewStatus;
    }

    /** @return list<string> */
    public function driftIssues(): array
    {
        return $this->driftIssues;
    }
}
