<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Persistence;

use App\Rulesets\Domain\GameSystem;
use App\Rulesets\Domain\GameSystemStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persistence model for the Rulesets context (see Constitution I note on
 * PersistenceUser). Flow + sheet live in jsonb columns; `version` is the
 * optimistic-lock column for supersede detection.
 *
 * @phpstan-import-type FlowPayload from RulesetJsonMapper
 * @phpstan-import-type SheetPayload from RulesetJsonMapper
 */
#[ORM\Entity]
#[ORM\Table(name: 'game_systems')]
#[ORM\UniqueConstraint(name: 'uniq_game_systems_name', columns: ['name'])]
class PersistenceGameSystem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'string', length: 16, enumType: GameSystemStatus::class)]
    private GameSystemStatus $status = GameSystemStatus::Active;

    /**
     * @var FlowPayload
     */
    #[ORM\Column(type: 'jsonb')]
    private array $flowDefinition;

    /**
     * @var SheetPayload|null
     */
    #[ORM\Column(type: 'jsonb', nullable: true)]
    private ?array $sheetStructure = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /**
     * @param FlowPayload      $flowDefinition
     * @param SheetPayload|null $sheetStructure
     */
    public function __construct(string $id, string $name, string $description, GameSystemStatus $status, array $flowDefinition, ?array $sheetStructure)
    {
        $this->id = $id;
        $this->replace($name, $description, $status, $flowDefinition, $sheetStructure);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): GameSystemStatus
    {
        return $this->status;
    }

    /** @return FlowPayload */
    public function flowDefinition(): array
    {
        return $this->flowDefinition;
    }

    /** @return SheetPayload|null */
    public function sheetStructure(): ?array
    {
        return $this->sheetStructure;
    }

    /**
     * Applies a domain snapshot. Never touches the @Version field — Doctrine
     * bumps it at flush time to detect superseded updates.
     *
     * @param FlowPayload      $flowDefinition
     * @param SheetPayload|null $sheetStructure
     */
    public function replace(string $name, string $description, GameSystemStatus $status, array $flowDefinition, ?array $sheetStructure): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
        $this->flowDefinition = $flowDefinition;
        $this->sheetStructure = $sheetStructure;
    }

    public function version(): int
    {
        return $this->version;
    }
}
