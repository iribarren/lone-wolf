<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * Aggregate root of the Rulesets context (FR-001..FR-006).
 * Every system owns exactly one campaign flow and an optional sheet
 * structure. Status changes never touch definition data, so existing
 * campaigns stay fully playable after deactivation (FR-006).
 */
final readonly class GameSystem
{
    private function __construct(
        private GameSystemId $id,
        private string $name,
        private string $description,
        private GameSystemStatus $status,
        private FlowDefinition $flowDefinition,
        private ?SheetStructure $sheetStructure,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Game system names must be non-empty.');
        }
    }

    public static function start(
        GameSystemId $id,
        string $name,
        string $description,
        FlowDefinition $flowDefinition,
    ): self {
        return new self($id, $name, $description, GameSystemStatus::Active, $flowDefinition, null);
    }

    /** @internal reconstitution only */
    public static function reconstitute(
        GameSystemId $id,
        string $name,
        string $description,
        GameSystemStatus $status,
        FlowDefinition $flowDefinition,
        ?SheetStructure $sheetStructure,
    ): self {
        return new self($id, $name, $description, $status, $flowDefinition, $sheetStructure);
    }

    public function id(): GameSystemId
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

    public function isActive(): bool
    {
        return $this->status === GameSystemStatus::Active;
    }

    public function flowDefinition(): FlowDefinition
    {
        return $this->flowDefinition;
    }

    public function sheetStructure(): ?SheetStructure
    {
        return $this->sheetStructure;
    }

    public function activate(): self
    {
        return $this->with(status: GameSystemStatus::Active);
    }

    public function deactivate(): self
    {
        return $this->with(status: GameSystemStatus::Inactive);
    }

    /**
     * Replaces the owned flow definition. Occupancy guarding (FR-005) is a
     * policy of the Application layer — it needs the StageOccupancyChecker
     * port and therefore does not belong inside the aggregate.
     */
    public function withFlowDefinition(FlowDefinition $flowDefinition): self
    {
        return $this->with(flowDefinition: $flowDefinition);
    }

    public function withSheetStructure(SheetStructure $sheetStructure): self
    {
        return $this->with(sheetStructure: $sheetStructure);
    }

    public function updateProfile(string $name, string $description): self
    {
        return $this->with(name: $name, description: $description);
    }

    private function with(
        ?GameSystemStatus $status = null,
        ?FlowDefinition $flowDefinition = null,
        ?SheetStructure $sheetStructure = null,
        ?string $name = null,
        ?string $description = null,
    ): self {
        return new self(
            $this->id,
            $name ?? $this->name,
            $description ?? $this->description,
            $status ?? $this->status,
            $flowDefinition ?? $this->flowDefinition,
            $sheetStructure ?? $this->sheetStructure,
        );
    }
}
