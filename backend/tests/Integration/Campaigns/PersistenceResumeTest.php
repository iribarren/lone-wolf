<?php

declare(strict_types=1);

namespace App\Tests\Integration\Campaigns;

use App\Campaigns\Application\Command\AdvanceStageCommand;
use App\Campaigns\Application\Command\AppendNarrativeEntryCommand;
use App\Campaigns\Application\Command\DeleteCampaignCommand;
use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\ConfirmationRequiredException;
use App\Campaigns\Application\DeleteCampaignHandler;
use App\Campaigns\Application\GetCampaignStateHandler;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\StartCampaignHandler;
use App\Campaigns\Domain\CampaignAccessDeniedException;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Journal\Application\ListJournalEntriesHandler;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * FR-018 (stop/resume restores exact state), FR-019 (owner-scoped reads,
 * never disclosing foreign campaigns) and FR-020 (irreversible confirmed
 * delete cascading journal history) against the real PostgreSQL adapters.
 */
final class PersistenceResumeTest extends KernelTestCase
{
    private CreateGameSystemHandler $createSystem;

    private StartCampaignHandler $startCampaign;

    private \App\Campaigns\Application\AdvanceStageHandler $advanceStage;

    private \App\Campaigns\Application\AppendNarrativeEntryHandler $appendNarrative;

    private GetCampaignStateHandler $campaignState;

    private ListJournalEntriesHandler $journalPage;

    private DeleteCampaignHandler $deleteCampaign;

    private CampaignRepositoryInterface $campaigns;

    private \App\Journal\Application\Port\JournalEntryRepositoryInterface $journalEntries;

    private UserRepositoryInterface $users;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $pull = static function (string $id) use ($container): object {
            $service = $container->get($id);
            \assert(\is_object($service));

            return $service;
        };

        /** @var CreateGameSystemHandler $createSystem */
        $createSystem = $pull(CreateGameSystemHandler::class);
        /** @var StartCampaignHandler $startCampaign */
        $startCampaign = $pull(StartCampaignHandler::class);
        /** @var \App\Campaigns\Application\AdvanceStageHandler $advanceStage */
        $advanceStage = $pull(\App\Campaigns\Application\AdvanceStageHandler::class);
        /** @var \App\Campaigns\Application\AppendNarrativeEntryHandler $appendNarrative */
        $appendNarrative = $pull(\App\Campaigns\Application\AppendNarrativeEntryHandler::class);
        /** @var GetCampaignStateHandler $campaignState */
        $campaignState = $pull(GetCampaignStateHandler::class);
        /** @var ListJournalEntriesHandler $journalPage */
        $journalPage = $pull(ListJournalEntriesHandler::class);
        /** @var DeleteCampaignHandler $deleteCampaign */
        $deleteCampaign = $pull(DeleteCampaignHandler::class);
        /** @var CampaignRepositoryInterface $campaigns */
        $campaigns = $pull(CampaignRepositoryInterface::class);
        /** @var \App\Journal\Application\Port\JournalEntryRepositoryInterface $journalEntries */
        $journalEntries = $pull(\App\Journal\Application\Port\JournalEntryRepositoryInterface::class);
        /** @var UserRepositoryInterface $users */
        $users = $pull(UserRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $pull(EntityManagerInterface::class);

        $this->createSystem = $createSystem;
        $this->startCampaign = $startCampaign;
        $this->advanceStage = $advanceStage;
        $this->appendNarrative = $appendNarrative;
        $this->campaignState = $campaignState;
        $this->journalPage = $journalPage;
        $this->deleteCampaign = $deleteCampaign;
        $this->campaigns = $campaigns;
        $this->journalEntries = $journalEntries;
        $this->users = $users;
        $this->entityManager = $entityManager;
    }

    public function testStopAndResumeRestoresExactStageAndJournal(): void
    {
        $player = $this->registerPlayer('resume');
        $systemId = $this->createSceneSequelSystem();

        $started = $this->startCampaign->handle(new StartCampaignCommand($player, $systemId));
        $campaignId = \App\Shared\Domain\Identifier\CampaignId::fromString($started->campaignId);

        self::assertSame('Scene', $started->currentStage->stageName);

        $this->appendNarrative->handle(new AppendNarrativeEntryCommand($player, $campaignId, 'A quiet opening at the Scene.'));
        $this->advanceStage->handle(new AdvanceStageCommand($player, $campaignId, 'Sequel'));
        $this->appendNarrative->handle(new AppendNarrativeEntryCommand($player, $campaignId, 'The Sequel resolves the tension.'));

        // Simulate the player closing the app: drop every in-memory handle.
        $this->entityManager->clear();

        $resumed = $this->campaignState->state($campaignId, $player);
        self::assertSame('Sequel', $resumed->currentStage->stageName);

        $page = $this->journalPage->handle(new ListJournalEntriesQuery($player, $campaignId));
        self::assertCount(2, $page->entries);
        self::assertNull($page->nextCursor);

        // Newest first; the stage snapshot survives exactly as written.
        self::assertSame(['Sequel', 'Scene'], array_map(
            static fn ($entry): string => $entry->stageName(),
            $page->entries,
        ));
        self::assertSame('The Sequel resolves the tension.', $page->entries[0]->narrative());
    }

    public function testForeignPlayersNeverSeeSomeoneElsesCampaign(): void
    {
        $owner = $this->registerPlayer('owner');
        $intruder = $this->registerPlayer('intruder');
        $systemId = $this->createSceneSequelSystem();

        $started = $this->startCampaign->handle(new StartCampaignCommand($owner, $systemId));
        $campaignId = \App\Shared\Domain\Identifier\CampaignId::fromString($started->campaignId);

        // Expected — never disclose existence (FR-019).
        $this->expectException(CampaignAccessDeniedException::class);
        $this->campaignState->state($campaignId, $intruder);
    }

    public function testConfirmedDeleteIsIrreversibleAndCascadesJournalEntries(): void
    {
        $player = $this->registerPlayer('delete');
        $systemId = $this->createSceneSequelSystem();

        $started = $this->startCampaign->handle(new StartCampaignCommand($player, $systemId));
        $campaignId = \App\Shared\Domain\Identifier\CampaignId::fromString($started->campaignId);
        $this->appendNarrative->handle(new AppendNarrativeEntryCommand($player, $campaignId, 'History to be erased.'));

        try {
            $this->deleteCampaign->handle(new DeleteCampaignCommand($player, $campaignId, confirm: false));
            self::fail('Deleting without the confirm flag must be refused.');
        } catch (ConfirmationRequiredException) {
            // Expected (FR-020).
        }

        $this->deleteCampaign->handle(new DeleteCampaignCommand($player, $campaignId, confirm: true));

        $this->entityManager->clear();

        self::assertNull($this->campaigns->get($campaignId));
        self::assertSame([], $this->journalEntries->page($campaignId, null, null, 10)->entries);
    }

    public function testJournalPaginationCursorWalksTheWholeHistory(): void
    {
        $player = $this->registerPlayer('paging');
        $systemId = $this->createSceneSequelSystem();

        $started = $this->startCampaign->handle(new StartCampaignCommand($player, $systemId));
        $campaignId = \App\Shared\Domain\Identifier\CampaignId::fromString($started->campaignId);

        for ($i = 1; $i <= 5; ++$i) {
            $this->appendNarrative->handle(new AppendNarrativeEntryCommand($player, $campaignId, sprintf('Entry %d.', $i)));
        }

        $firstPage = $this->journalPage->handle(new ListJournalEntriesQuery($player, $campaignId, limit: 3));
        self::assertCount(3, $firstPage->entries);
        self::assertNotNull($firstPage->nextCursor);

        $secondPage = $this->journalPage->handle(new ListJournalEntriesQuery(
            $player,
            $campaignId,
            cursor: $firstPage->nextCursor,
        ));
        self::assertCount(2, $secondPage->entries);
        self::assertNull($secondPage->nextCursor);

        $seen = array_map(static fn ($entry): string => (string) $entry->narrative(), [
            ...$firstPage->entries,
            ...$secondPage->entries,
        ]);
        self::assertSame(
            ['Entry 5.', 'Entry 4.', 'Entry 3.', 'Entry 2.', 'Entry 1.'],
            $seen,
        );
    }

    private function registerPlayer(string $prefix): UserId
    {
        $user = User::register(
            UserId::generate(),
            sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(4))),
            'integration-test-hash',
        );
        $this->users->save($user);

        return $user->id();
    }

    private function createSceneSequelSystem(): GameSystemId
    {
        return $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('Resume Test %s', uniqid('', true)),
            description: 'Persistence round-trip fixture.',
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [['from' => 'Scene', 'to' => 'Sequel']],
        ));
    }
}
