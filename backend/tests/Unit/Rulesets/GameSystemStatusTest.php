<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rulesets;

use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\GameSystem;
use App\Rulesets\Domain\GameSystemStatus;
use App\Shared\Domain\Identifier\GameSystemId;
use PHPUnit\Framework\TestCase;

final class GameSystemStatusTest extends TestCase
{
    private const FLOW_STAGES = ['Scene', 'Sequel'];

    private function system(): GameSystem
    {
        return GameSystem::start(
            GameSystemId::generate(),
            'Scene-Sequel',
            'Classic two-beat loop.',
            FlowDefinition::create(self::FLOW_STAGES, 'Scene', []),
        );
    }

    public function testNewSystemsStartActive(): void
    {
        self::assertTrue($this->system()->isActive());
        self::assertSame(GameSystemStatus::Active, $this->system()->status());
    }

    public function testDeactivationKeepsFlowAndSheetIntactSoExistingCampaignsStayPlayable(): void
    {
        $system = $this->system()->withSheetStructure(SheetStructureStub::structure());
        $deactivated = $system->deactivate();

        // FR-006: deactivation removes the system from *new-campaign* selection
        // only — the definition data campaigns rely on must never be mutated.
        self::assertFalse($deactivated->isActive());
        self::assertSame(
            $system->flowDefinition()->startingStage()->name(),
            $deactivated->flowDefinition()->startingStage()->name(),
        );
        self::assertSame(
            $system->flowDefinition()->stages(),
            $deactivated->flowDefinition()->stages(),
        );
        self::assertSame(
            $system->sheetStructure()?->version(),
            $deactivated->sheetStructure()?->version(),
        );
    }

    public function testActivationRoundTripIsIdempotentOnData(): void
    {
        $system = $this->system();

        self::assertTrue($system->deactivate()->activate()->isActive());
        self::assertTrue($system->deactivate()->deactivate()->deactivate()->isActive() === false);
        self::assertSame($system->flowDefinition(), $system->deactivate()->flowDefinition());
    }
}

final class SheetStructureStub
{
    public static function structure(): \App\Rulesets\Domain\SheetStructure
    {
        return \App\Rulesets\Domain\SheetStructure::create([
            FieldDefinition::text('name', 'Name'),
        ]);
    }
}
