<?php

declare(strict_types=1);

namespace App\Tests\Integration\Rulesets;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Application\UpdateFlowDefinitionHandler;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * FR-005: flow modifications leaving an occupied stage orphaned are refused
 * — occupancy comes from REAL campaigns started through the application
 * handlers; concurrent supersede conflicts surface as optimistic-lock
 * failures.
 */
final class FlowModificationGuardTest extends KernelTestCase
{
    private CreateGameSystemHandler $createHandler;

    private \App\Rulesets\Application\UpdateFlowDefinitionHandler $updateHandler;

    private RulesetRepositoryInterface $systems;

    private StartCampaignHandler $startCampaign;

    private UserRepositoryInterface $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $createHandler = $container->get(CreateGameSystemHandler::class);
        $updateHandler = $container->get(UpdateFlowDefinitionHandler::class);
        $systems = $container->get(RulesetRepositoryInterface::class);
        $startCampaign = $container->get(StartCampaignHandler::class);
        $users = $container->get(UserRepositoryInterface::class);

        \assert($createHandler instanceof CreateGameSystemHandler);
        \assert($updateHandler instanceof UpdateFlowDefinitionHandler);
        \assert($systems instanceof RulesetRepositoryInterface);
        \assert($startCampaign instanceof StartCampaignHandler);
        \assert($users instanceof UserRepositoryInterface);

        $this->createHandler = $createHandler;
        $this->updateHandler = $updateHandler;
        $this->systems = $systems;
        $this->startCampaign = $startCampaign;
        $this->users = $users;
    }

    public function testRemovingAnOccupiedStageIsRefused(): void
    {
        $id = $this->createSystem('guard-'.uniqid());
        $this->occupyOpeningStage($id);

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
        $this->occupyOpeningStage($id);

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
            array_map(static fn ($s): string => $s->name(), $reloaded->flowDefinition()->stages()),
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

    /**
     * Occupies the system's opening stage the way players actually do: by
     * starting a campaign on it (FR-005 ground truth).
     */
    private function occupyOpeningStage(GameSystemId $systemId): void
    {
        $player = User::register(UserId::generate(), sprintf('guard-%s@example.com', bin2hex(random_bytes(4))), 'hash');
        $this->users->save($player);

        $this->startCampaign->handle(new StartCampaignCommand($player->id(), $systemId));
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
