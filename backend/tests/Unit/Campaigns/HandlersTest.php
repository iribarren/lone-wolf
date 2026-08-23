<?php

declare(strict_types=1);

namespace App\Tests\Unit\Campaigns;

use App\Campaigns\Application\Command\AdvanceStageCommand;
use App\Campaigns\Application\Command\AppendNarrativeEntryCommand;
use App\Campaigns\Application\Command\DeleteCampaignCommand;
use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\ConfirmationRequiredException;
use App\Campaigns\Application\DeleteCampaignHandler;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\Port\FlowDefinitionProviderInterface;
use App\Campaigns\Application\StartCampaignHandler;
use App\Campaigns\Application\SystemNotPlayableException;
use App\Campaigns\Domain\Campaign;
use App\Campaigns\Domain\CampaignAccessDeniedException;
use App\Campaigns\Domain\CampaignNotFoundException;
use App\Campaigns\Domain\FlowEdge;
use App\Campaigns\Domain\FlowGraph;
use App\Campaigns\Domain\FlowStageNode;
use App\Campaigns\Domain\SuggestedActionKind;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Shared\Domain\ClockInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

/**
 * US2 application-layer behaviour, exercised with in-memory fakes only —
 * no kernel, no Doctrine (Constitution IV).
 */
final class HandlersTest extends TestCase
{
    private const PLAYER_ID = '11111111-1111-4111-8111-111111111111';
    private const OTHER_PLAYER_ID = '22222222-2222-4222-8222-222222222222';
    private const SYSTEM_ID = '33333333-3333-4333-8333-333333333333';

    public function testStartCampaignPositionsOnTheDesignatedStartingStage(): void
    {
        $harness = $this->harness();

        $state = $harness->startCampaign->handle(new StartCampaignCommand(
            self::playerId(),
            self::systemId(),
        ));

        self::assertSame('Scene', $state->currentStage->stageName);
        self::assertStringContainsString('Open your Scene.', $state->currentStage->guidance);
        self::assertSame(self::SYSTEM_ID, $state->gameSystemId);
        self::assertCount(1, $harness->campaigns->all());
    }

    public function testStartCampaignRefusesAnInactiveSystem(): void
    {
        $harness = $this->harness();
        $harness->flowProvider->active = false;

        $this->expectException(SystemNotPlayableException::class);
        $this->expectExceptionMessage('inactive');

        $harness->startCampaign->handle(new StartCampaignCommand(self::playerId(), self::systemId()));
    }

    public function testStartCampaignRefusesAnUnknownSystem(): void
    {
        $harness = $this->harness();

        $this->expectException(SystemNotPlayableException::class);
        $this->expectExceptionMessage('unknown');

        $harness->startCampaign->handle(new StartCampaignCommand(self::playerId(), GameSystemId::generate()));
    }

    public function testAdvanceRefusalPayloadCarriesLegalAlternatives(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        try {
            $harness->advanceStage->handle(new AdvanceStageCommand(self::playerId(), $campaignId, 'Setup'));
            self::fail('Expected the illegal move to be refused.');
        } catch (\App\Campaigns\Domain\IllegalStageTransitionException $refusal) {
            self::assertSame(['Sequel'], array_map(
                static fn ($action): ?string => $action->toStageName,
                $refusal->legalAlternatives(),
            ));
        }
    }

    public function testAppendNarrativeEntryStampsTheCurrentStage(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        $entry = $harness->appendNarrative->handle(new AppendNarrativeEntryCommand(
            self::playerId(),
            $campaignId,
            'The tavern door creaks open.',
        ));

        self::assertSame('Scene', $entry->stageName());
        self::assertSame('narrative', $entry->kind()->value);
        self::assertSame($campaignId->toString(), $entry->campaignId()->toString());
        self::assertSame('The tavern door creaks open.', $entry->narrative());
        self::assertCount(1, $harness->journalEntries->entriesFor($campaignId));
    }

    public function testAnotherPlayerCannotReadOrMoveSomeoneElsesCampaign(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        $this->expectException(CampaignAccessDeniedException::class);

        $harness->advanceStage->handle(new AdvanceStageCommand(self::otherPlayerId(), $campaignId, 'Sequel'));
    }

    public function testMissingCampaignSurfacesAsNotFound(): void
    {
        $harness = $this->harness();

        $this->expectException(CampaignNotFoundException::class);

        $harness->advanceStage->handle(new AdvanceStageCommand(self::playerId(), CampaignId::generate(), 'Sequel'));
    }

    public function testDeleteWithoutConfirmFlagIsRefused(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        $this->expectException(ConfirmationRequiredException::class);

        $harness->deleteCampaign->handle(new DeleteCampaignCommand(self::playerId(), $campaignId, confirm: false));
    }

    /**
     * Collaborators shared by every handler test.
     */
    private function harness(): Harness
    {
        return new Harness(
            new InMemoryCampaignRepository(),
            new InMemoryJournalEntryRepository(),
            new StaticFlowProvider(),
            new FixedClock(),
        );
    }

    private static function playerId(): UserId
    {
        return UserId::fromString(self::PLAYER_ID);
    }

    private static function otherPlayerId(): UserId
    {
        return UserId::fromString(self::OTHER_PLAYER_ID);
    }

    private static function systemId(): GameSystemId
    {
        return GameSystemId::fromString(self::SYSTEM_ID);
    }
}

/**
 * Mutable fixture wiring: the flow provider can be flipped inactive to
 * exercise FR-012.
 */
final class Harness
{
    public InMemoryCampaignRepository $campaigns;

    public InMemoryJournalEntryRepository $journalEntries;

    public StartCampaignHandler $startCampaign;

    public \App\Campaigns\Application\AdvanceStageHandler $advanceStage;

    public \App\Campaigns\Application\AppendNarrativeEntryHandler $appendNarrative;

    public DeleteCampaignHandler $deleteCampaign;

    public StaticFlowProvider $flowProvider;

    public function __construct(
        InMemoryCampaignRepository $campaigns,
        InMemoryJournalEntryRepository $journalEntries,
        StaticFlowProvider $flowProvider,
        FixedClock $clock,
    ) {
        $engine = new \App\Campaigns\Domain\FlowEngine();
        $this->campaigns = $campaigns;
        $this->journalEntries = $journalEntries;
        $this->flowProvider = $flowProvider;

        $this->startCampaign = new StartCampaignHandler($campaigns, $flowProvider, $engine, $clock);
        $this->advanceStage = new \App\Campaigns\Application\AdvanceStageHandler($campaigns, $flowProvider, $engine, $clock);
        $this->appendNarrative = new \App\Campaigns\Application\AppendNarrativeEntryHandler($campaigns, $journalEntries, $clock);
        $this->deleteCampaign = new DeleteCampaignHandler($campaigns, $journalEntries);
    }

    /** Starts a campaign on the fixture system and returns its id. */
    public function startedCampaignId(): CampaignId
    {
        $state = $this->startCampaign->handle(new StartCampaignCommand(
            UserId::fromString('11111111-1111-4111-8111-111111111111'),
            GameSystemId::fromString('33333333-3333-4333-8333-333333333333'),
        ));

        return CampaignId::fromString($state->campaignId);
    }
}

final class InMemoryCampaignRepository implements CampaignRepositoryInterface
{
    /** @var array<string, Campaign> */
    private array $byId = [];

    #[\Override]
    public function add(Campaign $campaign): void
    {
        $this->byId[$campaign->id()->toString()] = $campaign;
    }

    #[\Override]
    public function get(\App\Shared\Domain\Identifier\CampaignId $id): ?Campaign
    {
        return $this->byId[$id->toString()] ?? null;
    }

    #[\Override]
    public function delete(\App\Shared\Domain\Identifier\CampaignId $id): void
    {
        unset($this->byId[$id->toString()]);
    }

    #[\Override]
    public function ownedBy(UserId $playerId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Campaign $campaign): bool => $campaign->isOwnedBy($playerId),
        ));
    }

    /** @return list<Campaign> */
    public function all(): array
    {
        return array_values($this->byId);
    }
}

final class InMemoryJournalEntryRepository implements JournalEntryRepositoryInterface
{
    /** @var list<\App\Journal\Domain\JournalEntry> */
    private array $entries = [];

    #[\Override]
    public function add(\App\Journal\Domain\JournalEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[\Override]
    public function page(\App\Shared\Domain\Identifier\CampaignId $campaignId, ?string $stageName, ?string $cursor, int $limit): \App\Journal\Application\Query\JournalPage
    {
        $filtered = array_values(array_filter(
            $this->entries,
            static fn (\App\Journal\Domain\JournalEntry $entry): bool => $stageName === null || $entry->stageName() === $stageName,
        ));

        return new \App\Journal\Application\Query\JournalPage($filtered, null);
    }

    #[\Override]
    public function deleteAllForCampaign(\App\Shared\Domain\Identifier\CampaignId $campaignId): void
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (\App\Journal\Domain\JournalEntry $entry): bool => !$entry->campaignId()->equals($campaignId),
        ));
    }

    /** @return list<\App\Journal\Domain\JournalEntry> */
    public function entriesFor(\App\Shared\Domain\Identifier\CampaignId $campaignId): array
    {
        return $this->page($campaignId, null, null, 100)->entries;
    }
}

final class StaticFlowProvider implements FlowDefinitionProviderInterface
{
    public bool $active = true;

    #[\Override]
    public function forSystem(GameSystemId $gameSystemId): ?FlowGraph
    {
        if ($gameSystemId->toString() !== '33333333-3333-4333-8333-333333333333') {
            return null;
        }

        return new FlowGraph(
            stages: [
                new FlowStageNode('Scene', 'Open your Scene.'),
                new FlowStageNode('Sequel', 'Run the Sequel.'),
            ],
            edges: [new FlowEdge('Scene', 'Sequel')],
            startingStage: 'Scene',
            active: $this->active,
            systemName: 'Fixture System',
        );
    }
}

final class FixedClock implements ClockInterface
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-23T12:00:00+00:00');
    }
}
