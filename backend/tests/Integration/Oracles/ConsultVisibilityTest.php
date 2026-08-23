<?php

declare(strict_types=1);

namespace App\Tests\Integration\Oracles;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Journal\Application\ListJournalEntriesHandler;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Oracles\Application\Command\SaveConsultationToJournal;
use App\Oracles\Application\ConsultOracleHandler;
use App\Oracles\Application\SaveConsultationToJournalHandler;
use App\Oracles\Domain\GlobalScope;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\SystemScope;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * US4 consultation surface against the real adapters:
 *
 * - the consult handler answers only for oracles visible to the campaign's
 *   system (FR-009) and degrades gracefully — a foreign or retired table
 *   yields an `unavailable` outcome, never an error (Edge Cases §3);
 * - an empty table consults to the friendly empty-table path (FR-011);
 * - a saved result lands in the journal as an oracle_result entry carrying
 *   the denormalized {oracleTitle, resultText} snapshot (US4 scenario 3).
 */
final class ConsultVisibilityTest extends KernelTestCase
{
    private ConsultOracleHandler $consult;

    private SaveConsultationToJournalHandler $saveToJournal;


    private CreateGameSystemHandler $createSystem;
    private StartCampaignHandler $startCampaign;

    private \App\Oracles\Application\Port\OracleRepositoryInterface $oracles;

    private UserRepositoryInterface $users;

    private ListJournalEntriesHandler $journalPage;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $pull = static function (string $id) use ($container): object {
            $service = $container->get($id);
            \assert(\is_object($service));

            return $service;
        };

        /** @var ConsultOracleHandler $consult */
        $consult = $pull(ConsultOracleHandler::class);
        /** @var SaveConsultationToJournalHandler $saveToJournal */
        $saveToJournal = $pull(SaveConsultationToJournalHandler::class);
        /** @var CreateGameSystemHandler $createSystem */
        $createSystem = $pull(CreateGameSystemHandler::class);
        /** @var StartCampaignHandler $startCampaign */
        $startCampaign = $pull(StartCampaignHandler::class);
        /** @var \App\Oracles\Application\Port\OracleRepositoryInterface $oracles */
        $oracles = $pull(\App\Oracles\Application\Port\OracleRepositoryInterface::class);
        /** @var UserRepositoryInterface $users */
        $users = $pull(UserRepositoryInterface::class);
        /** @var ListJournalEntriesHandler $journalPage */
        $journalPage = $pull(ListJournalEntriesHandler::class);

        $this->consult = $consult;
        $this->saveToJournal = $saveToJournal;
        $this->createSystem = $createSystem;
        $this->startCampaign = $startCampaign;
        $this->oracles = $oracles;
        $this->users = $users;
        $this->journalPage = $journalPage;
    }

    public function testScopedTableAnswersOwnSystemAndDegradesForForeign(): void
    {
        [$player, $systemA, $systemB] = $this->twoSystemsFixture();
        $campaignA = $this->campaignOn($player, $systemA);
        $campaignB = $this->campaignOn($player, $systemB);

        $scopedId = $this->scopedOracle('Encounters', $systemA)->id();

        // Own system: exactly one weighted-random answer (FR-010).
        $own = $this->consult->handle($player, $campaignA, $scopedId);
        self::assertTrue($own->isSelected());

        // Foreign system: graceful unavailable outcome (FR-009 / Edge §3).
        $foreign = $this->consult->handle($player, $campaignB, $scopedId);
        self::assertTrue($foreign->isUnavailable());
        self::assertNull($foreign->selected());
    }

    public function testGlobalTableAnswersEveryCampaign(): void
    {
        [$player, $systemA, $systemB] = $this->twoSystemsFixture();

        $weather = Oracle::start(
            \App\Shared\Domain\Identifier\OracleId::generate(),
            'Weather '.$this->suffix(),
            new GlobalScope(),
        )->addEntry('Clear skies.', 3)
            ->addEntry('Storm rolling in.', 1);
        $this->oracles->save($weather);

        foreach ([$this->campaignOn($player, $systemA), $this->campaignOn($player, $systemB)] as $campaignId) {
            $outcome = $this->consult->handle($player, $campaignId, $weather->id());
            self::assertTrue($outcome->isSelected(), 'Global tables answer every campaign.');
        }
    }

    public function testRetiredOracleConsultsGracefullyUnavailable(): void
    {
        [$player] = $this->twoSystemsFixture();
        $campaign = $this->campaignOn($player, $this->createSceneSequelSystem('retired'));

        // A table that no longer exists (retired/purged) must not 500.
        $outcome = $this->consult->handle($player, $campaign, \App\Shared\Domain\Identifier\OracleId::generate());

        self::assertTrue($outcome->isUnavailable());
        self::assertNotNull($outcome->reason());
    }

    public function testEmptyTableConsultsToFriendlyOutcome(): void
    {
        [$player] = $this->twoSystemsFixture();
        $campaign = $this->campaignOn($player, $this->createSceneSequelSystem('empty'));

        $empty = Oracle::start(
            \App\Shared\Domain\Identifier\OracleId::generate(),
            'Blank table '.$this->suffix(),
            new GlobalScope(),
        );
        $this->oracles->save($empty);

        $outcome = $this->consult->handle($player, $campaign, $empty->id());

        self::assertTrue($outcome->isEmptyTable(), 'An empty table is a notice, not a failure (FR-011).');
    }

    public function testSavedResultLandsInJournalWithSnapshot(): void
    {
        [$player] = $this->twoSystemsFixture();
        $campaign = $this->campaignOn($player, $this->createSceneSequelSystem('save'));

        $title = 'Weather '.$this->suffix();
        $this->saveToJournal->handle(new SaveConsultationToJournal(
            $player,
            $campaign,
            $title,
            'A cold rain sets in.',
        ));

        $page = $this->journalPage->handle(new ListJournalEntriesQuery($player, $campaign));

        self::assertCount(1, $page->entries);
        $entry = $page->entries[0];
        self::assertSame('oracle_result', $entry->kind()->value);
        $snapshot = $entry->oracleSnapshot();
        self::assertNotNull($snapshot);
        self::assertSame($title, $snapshot->oracleTitle);
        self::assertSame('A cold rain sets in.', $snapshot->resultText);
    }

    /**
     * @return array{UserId, GameSystemId, GameSystemId}
     */
    private function twoSystemsFixture(): array
    {
        $player = $this->registerPlayer();

        return [
            $player,
            $this->createSceneSequelSystem('a'),
            $this->createActLadderSystem(),
        ];
    }

    private function registerPlayer(): UserId
    {
        $user = User::register(
            UserId::generate(),
            sprintf('consult-%s@example.com', bin2hex(random_bytes(4))),
            'integration-test-hash',
        );
        $this->users->save($user);

        return $user->id();
    }

    private function createSceneSequelSystem(string $prefix): GameSystemId
    {
        return $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('%s-%s', $prefix, bin2hex(random_bytes(4))),
            description: 'Consult visibility fixture.',
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [],
        ));
    }

    private function createActLadderSystem(): GameSystemId
    {
        return $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('act-ladder-%s', bin2hex(random_bytes(4))),
            description: 'Consult visibility fixture.',
            stageNames: ['Act I', 'Beat', 'Act II'],
            startingStage: 'Act I',
            transitions: [],
        ));
    }

    private function campaignOn(UserId $player, GameSystemId $systemId): \App\Shared\Domain\Identifier\CampaignId
    {
        $started = $this->startCampaign->handle(new StartCampaignCommand($player, $systemId));

        return \App\Shared\Domain\Identifier\CampaignId::fromString($started->campaignId);
    }

    private function scopedOracle(string $base, GameSystemId $systemId): Oracle
    {
        $oracle = Oracle::start(
            \App\Shared\Domain\Identifier\OracleId::generate(),
            sprintf('%s %s', $base, $this->suffix()),
            new SystemScope($systemId),
        )->addEntry('Ambush.', 4)
            ->addEntry('Quiet trail.', 1);
        $this->oracles->save($oracle);

        return $oracle;
    }

    /** Integration storage persists across runs — unique suffixes avoid collisions. */
    private function suffix(): string
    {
        return bin2hex(random_bytes(3));
    }
}
