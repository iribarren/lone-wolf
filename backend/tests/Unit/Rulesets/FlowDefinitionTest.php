<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rulesets;

use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\FlowTransition;
use PHPUnit\Framework\TestCase;

final class FlowDefinitionTest extends TestCase
{
    public function testAcceptsAWellFormedTwoStageFlow(): void
    {
        $flow = FlowDefinition::create(
            ['Departure', 'Scene'],
            'Departure',
            [FlowTransition::fromNames('Departure', 'Scene')],
        );

        self::assertSame('Departure', $flow->startingStage()->name());
        self::assertCount(2, $flow->stages());
        self::assertSame(['Scene'], $flow->legalNextStages('Departure'));
    }

    public function testRefusesFewerThanTwoStages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least two');

        FlowDefinition::create(['OnlyOne'], 'OnlyOne', []);
    }

    public function testRefusesDuplicateStageNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unique');

        FlowDefinition::create(['Scene', 'Scene'], 'Scene', []);
    }

    public function testRefusesEmptyStageName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty');

        FlowDefinition::create(['Scene', '   '], 'Scene', []);
    }

    public function testRefusesUnknownStartingStage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('starting');

        FlowDefinition::create(['Scene', 'Sequel'], 'Nonsense', []);
    }

    public function testRefusesTransitionReferencingUnknownStage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown stage');

        FlowDefinition::create(
            ['Scene', 'Sequel'],
            'Scene',
            [FlowTransition::fromNames('Scene', 'Ghost')],
        );
    }

    public function testLegalNextStagesReturnsEmptyListForDeadEnd(): void
    {
        $flow = FlowDefinition::create(['Scene', 'Sequel'], 'Scene', []);

        self::assertSame([], $flow->legalNextStages('Sequel'));
    }
}
