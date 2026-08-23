<?php

declare(strict_types=1);

namespace App\Tests\Unit\Campaigns;

use App\Campaigns\Domain\FlowEdge;
use App\Campaigns\Domain\FlowEngine;
use App\Campaigns\Domain\FlowGraph;
use App\Campaigns\Domain\FlowStageNode;
use App\Campaigns\Domain\IllegalStageTransitionException;
use App\Campaigns\Domain\SuggestedActionKind;
use App\Campaigns\Domain\StagePosition;
use App\Shared\Domain\Identifier\GameSystemId;
use PHPUnit\Framework\TestCase;

/**
 * FR-016 / US2: the flow engine is a pure state machine over an
 * admin-authored graph — acts, scenes and beats are DATA, never code states.
 */
final class FlowEngineTest extends TestCase
{
    private const SYSTEM_ID = '5f0b8a43-1c1d-4e6a-9f2b-3d4e5f607182';

    private FlowEngine $engine;

    private FlowGraph $sceneSequel;

    protected function setUp(): void
    {
        $this->engine = new FlowEngine();
        $this->sceneSequel = self::graph(
            stages: [
                new FlowStageNode('Setup', 'Prepare tonight\'s session.'),
                new FlowStageNode('Scene', 'Open your Scene.'),
                new FlowStageNode('Sequel', 'Run the Sequel.'),
            ],
            startingStage: 'Scene',
            edges: [
                new FlowEdge('Scene', 'Sequel'),
                new FlowEdge('Sequel', 'Scene'),
                new FlowEdge('Setup', 'Scene'),
            ],
        );
    }

    public function testLegalNextStagesReturnsDirectSuccessorsOnly(): void
    {
        $position = self::positionAt('Scene');

        self::assertSame(['Sequel'], $this->engine->legalNextStages($this->sceneSequel, $position));
        self::assertSame(['Scene'], $this->engine->legalNextStages($this->sceneSequel, self::positionAt('Sequel')));
    }

    public function testLegalNextStagesIsEmptyOnTerminalStage(): void
    {
        $deadEnd = self::graph(
            stages: [new FlowStageNode('Free Play', 'Wander freely.')],
            startingStage: 'Free Play',
            edges: [],
        );

        self::assertSame([], $this->engine->legalNextStages($deadEnd, self::positionAt('Free Play')));
    }

    public function testAssertCanAdvanceAcceptsALegalTransition(): void
    {
        $this->engine->assertCanAdvance($this->sceneSequel, self::positionAt('Scene'), 'Sequel');

        // Reaching this line without an exception IS the assertion.
        $this->addToAssertionCount(1);
    }

    public function testIllegalAdvanceCarriesTheLegalAlternatives(): void
    {
        try {
            $this->engine->assertCanAdvance($this->sceneSequel, self::positionAt('Scene'), 'Setup');
            self::fail('An illegal transition must be refused.');
        } catch (IllegalStageTransitionException $exception) {
            self::assertSame('Scene', $exception->fromStage());
            self::assertSame('Setup', $exception->attemptedStage());

            $alternatives = $exception->legalAlternatives();
            self::assertCount(1, $alternatives);
            self::assertSame(SuggestedActionKind::Advance, $alternatives[0]->kind);
            self::assertSame('Sequel', $alternatives[0]->toStageName);
        }
    }

    public function testIllegalAdvanceFromATerminalStageExplainsThereAreNoOptions(): void
    {
        $deadEnd = self::graph(
            stages: [new FlowStageNode('Free Play', 'Wander freely.')],
            startingStage: 'Free Play',
            edges: [],
        );

        try {
            $this->engine->assertCanAdvance($deadEnd, self::positionAt('Free Play'), 'Anywhere');
            self::fail('Advancing out of a terminal stage must be refused.');
        } catch (IllegalStageTransitionException $exception) {
            self::assertSame([], $exception->legalAlternatives());
            self::assertStringContainsStringIgnoringCase('conclude', $exception->getMessage());
        }
    }

    public function testRefusalMessageListsEveryLegalAlternative(): void
    {
        $hub = self::graph(
            stages: [
                new FlowStageNode('Crossroads', ''),
                new FlowStageNode('Forest', ''),
                new FlowStageNode('Keep', ''),
            ],
            startingStage: 'Crossroads',
            edges: [
                new FlowEdge('Crossroads', 'Forest'),
                new FlowEdge('Crossroads', 'Keep'),
            ],
        );

        try {
            $this->engine->assertCanAdvance($hub, self::positionAt('Crossroads'), 'Castle');
            self::fail('Unknown target stages must be refused.');
        } catch (IllegalStageTransitionException $exception) {
            self::assertSame(['Forest', 'Keep'], array_map(
                static fn ($action): ?string => $action->toStageName,
                $exception->legalAlternatives(),
            ));
            self::assertStringContainsString('Forest', $exception->getMessage());
            self::assertStringContainsString('Keep', $exception->getMessage());
        }
    }

    public function testGuidanceOffersAdvanceActionsDerivedFromOutgoingEdges(): void
    {
        $guidance = $this->engine->guidance($this->sceneSequel, self::positionAt('Scene'));

        self::assertSame('Scene', $guidance->stageName);
        self::assertStringContainsString('Open your Scene.', $guidance->prompt);
        self::assertCount(1, $guidance->suggestedActions);
        self::assertSame(SuggestedActionKind::Advance, $guidance->suggestedActions[0]->kind);
        self::assertSame('Sequel', $guidance->suggestedActions[0]->toStageName);
    }

    public function testTerminalPositionYieldsConcludeGuidanceInsteadOfAdvanceActions(): void
    {
        $deadEnd = self::graph(
            stages: [new FlowStageNode('Free Play', 'Wander freely.')],
            startingStage: 'Free Play',
            edges: [],
        );

        $guidance = $this->engine->guidance($deadEnd, self::positionAt('Free Play'));

        self::assertCount(1, $guidance->suggestedActions);
        self::assertSame(SuggestedActionKind::Conclude, $guidance->suggestedActions[0]->kind);
        self::assertNull($guidance->suggestedActions[0]->toStageName);
        self::assertStringContainsString('Wander freely.', $guidance->prompt);
    }

    /**
     * @param list<FlowStageNode>          $stages
     * @param list<FlowEdge>               $edges
     */
    private static function graph(array $stages, string $startingStage, array $edges): FlowGraph
    {
        return new FlowGraph(
            stages: $stages,
            edges: $edges,
            startingStage: $startingStage,
            active: true,
            systemName: 'Fixture System',
        );
    }

    private static function positionAt(string $stageName): StagePosition
    {
        return new StagePosition(GameSystemId::fromString(self::SYSTEM_ID), $stageName);
    }
}
