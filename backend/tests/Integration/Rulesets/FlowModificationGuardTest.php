<?php

declare(strict_types=1);

namespace App\Tests\Integration\Rulesets;

use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Application\Port\StageOccupancyCheckerInterface;
use App\Shared\Domain\Identifier\GameSystemId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * FR-005: flow modifications leaving an occupied stage orphaned are refused;
 * concurrent supersede conflicts surface as optimistic-lock failures.
 */
final class FlowModificationGuardTest extends KernelTestCase
{
    private CreateGameSystemHandler $createHandler;
    private \App\Rulesets\Application\UpdateFlowDefinitionHandler $updateHandler;
    private RulesetRepositoryInterface $systems;
    private \App\Rulesets\Infrastructure\Persistence\InMemoryStageOccupancyChecker $checker;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $createHandler = $container->get(CreateGameSystemHandler::class);
        $updateHandler = $container->get(\App\Rulesets\Application\UpdateFlowDefinitionHandler::class);
        $systems = $container->get(RulesetRepositoryInterface::class);
        $checker = $container->get(StageOccupancyCheckerInterface::class);

        \assert($createHandler instanceof CreateGameSystemHandler);
        \assert($updateHandler instanceof \App\Rulesets\Application\UpdateFlowDefinitionHandler);
        \assert($systems instanceof RulesetRepositoryInterface);
        \assert($checker instanceof \App\Rulesets\Infrastructure\Persistence\InMemoryStageOccupancyChecker);

        $this->createHandler = $createHandler;
        $this->updateHandler = $updateHandler;
        $this->systems = $systems;
        $this->checker = $checker;
    }

    public function testRemovingAnOccupiedStageIsRefused(): void
    {
        $id = $this->createSystem('guard-'.uniqid());

        $this->checker->markOccupied($id, 'Scene');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('occupied');

        $this->updateHandler->handle(new UpdateFlowDefinitionCommand(
            $id,
            ['Renamed', 'Sequel'],
            'Renamed',
            [],
        ));
    }

    public function testModificationKeepingAllStagesIsAccepted(): void
    {
        $id = $this->createSystem('keep-'.uniqid());
        $this->checker->markOccupied($id, 'Scene');

        $this->updateHandler->handle(new UpdateFlowDefinitionCommand(
            $id,
            ['Scene', 'Sequel'],
            'Scene',
            [['from' => 'Scene', 'to' => 'Sequel']],
        ));

        $reloaded = $this->systems->get($id);
        self::assertNotNull($reloaded);
        self::assertSame(
            ['Scene', 'Sequel'],
            array_map(static fn ($s) => $s->name(), $reloaded->flowDefinition()->stages()),
        );
        self::assertSame(['Sequel'], $reloaded->flowDefinition()->legalNextStages('Scene'));
    }

    public function testSupersededUpdateSurfacesAsOptimisticLockFailure(): void
    {
        $id = $this->createSystem('lock-'.uniqid());

        /** @var \App\Rulesets\Domain\GameSystem $first */
        $first = $this->systems->get($id);

        // Simulate another admin committing a change behind our back.
        $registry = static::getContainer()->get('doctrine');
        \assert($registry instanceof \Doctrine\Persistence\ManagerRegistry);
        $connection = $registry->getConnection();
        \assert($connection instanceof \Doctrine\DBAL\Connection);
        $connection->executeStatement('UPDATE game_systems SET version = version + 1');

        $stale = $first->deactivate();

        $this->expectException(\Doctrine\ORM\OptimisticLockException::class);
        $this->systems->save($stale);
    }

    private function createSystem(string $name): GameSystemId
    {
        return $this->createHandler->handle(new CreateGameSystemCommand(
            name: $name,
            description: 'Guard fixture.',
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [],
        ));
    }
}
