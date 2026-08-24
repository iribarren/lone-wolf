<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dice;

use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Domain\Campaign;
use App\Campaigns\Domain\CampaignAccessDeniedException;
use App\Campaigns\Domain\StagePosition;
use App\Dice\Application\Command\RollAndLogToJournal;
use App\Dice\Application\RollAndLogHandler;
use App\Dice\Application\RollDiceHandler;
use App\Dice\Domain\DiceNotationFailureReason;
use App\Dice\Domain\DiceRoller;
use App\Dice\Domain\InvalidDiceNotationException;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Journal\Domain\JournalEntry;
use App\Shared\Domain\ClockInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\RandomSourceInterface;
use PHPUnit\Framework\TestCase;

/**
 * US6 application layer (T090) over in-memory fakes only — no kernel, no
 * Doctrine (Constitution IV): strict pre-roll refusals, ownership gate,
 * and dice_roll entries stamped with the CURRENT stage (FR-029).
 */
final class DiceHandlersTest extends TestCase
{
    private const PLAYER_ID = '11111111-1111-4111-8111-111111111111';

    private const OTHER_PLAYER_ID = '22222222-2222-4222-8222-222222222222';

    public function testRollRefusesMalformedNotationBeforeAnyDieIsThrown(): void
    {
        try {
            $this->rollHandler()->handle('2d');

            self::fail('Expected "2d" to be refused pre-roll.');
        } catch (InvalidDiceNotationException $refusal) {
            self::assertSame(DiceNotationFailureReason::Malformed, $refusal->reason());
        }
    }

    public function testRollAppliesTheModifierToTheSingleDie(): void
    {
        // Seeded source always yields 14 on a d20.
        $roll = $this->rollHandler(new AlwaysFaceRandomSource(14))->handle('1d20+5');

        self::assertSame([14], $roll->diceValues());
        self::assertSame(19, $roll->total());
        self::assertSame('2026-08-24T12:00:00+00:00', $roll->rolledAt()->format(\DateTimeInterface::ATOM));
    }

    public function testLogStampsWithTheCurrentStageAndPersistsTheSnapshot(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        $logged = $harness->logHandler->handle(new RollAndLogToJournal(
            self::player(),
            $campaignId,
            '3d6-2',
        ));

        $entry = $logged->entry;
        self::assertSame('dice_roll', $entry->kind()->value);
        self::assertSame('Scene', $entry->stageName());
        self::assertSame($campaignId->toString(), $entry->campaignId()->toString());

        $snapshot = $entry->rollSnapshot();
        self::assertNotNull($snapshot);
        self::assertSame('3d6-2', $snapshot->notation);
        self::assertCount(3, $snapshot->diceValues);
        self::assertSame(-2, $snapshot->modifier);
        self::assertSame(array_sum($snapshot->diceValues) - 2, $snapshot->total);

        self::assertSame(1, $harness->journalEntries->count());
        self::assertSame($logged->roll->total(), $snapshot->total);
    }

    public function testForeignPlayerCannotLogIntoSomeoneElsesCampaign(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        try {
            $harness->logHandler->handle(new RollAndLogToJournal(
                UserId::fromString(self::OTHER_PLAYER_ID),
                $campaignId,
                '2d6',
            ));

            self::fail('Expected the foreign player to be refused.');
        } catch (CampaignAccessDeniedException) {
            self::assertSame(0, $harness->journalEntries->count(), 'No entry may be logged for a foreign campaign.');
        }
    }

    public function testInvalidNotationOnTheLogPathPersistsNothing(): void
    {
        $harness = $this->harness();
        $campaignId = $harness->startedCampaignId();

        try {
            $harness->logHandler->handle(new RollAndLogToJournal(self::player(), $campaignId, '0d6'));

            self::fail('Expected "0d6" to be refused.');
        } catch (InvalidDiceNotationException $refusal) {
            self::assertSame(DiceNotationFailureReason::InvalidCount, $refusal->reason());
            self::assertSame(0, $harness->journalEntries->count(), 'A refused roll must not reach the journal.');
        }
    }

    private function rollHandler(?AlwaysFaceRandomSource $random = null): RollDiceHandler
    {
        return new RollDiceHandler(
            new DiceRoller($random ?? new AlwaysFaceRandomSource(6)),
            new FrozenTestClock(),
        );
    }

    private function harness(): DiceHarness
    {
        return new DiceHarness();
    }

    private static function player(): UserId
    {
        return UserId::fromString(self::PLAYER_ID);
    }
}

/**
 * Fixture wiring shared by the dice handler tests.
 */
final class DiceHarness
{
    public InMemoryDiceCampaignRepository $campaigns;

    public CountingJournalRepository $journalEntries;

    public RollAndLogHandler $logHandler;

    private readonly RollDiceHandler $rollDice;

    public function __construct()
    {
        $clock = new FrozenTestClock();
        $this->campaigns = new InMemoryDiceCampaignRepository();
        $this->journalEntries = new CountingJournalRepository();
        $this->rollDice = new RollDiceHandler(new DiceRoller(new AlwaysFaceRandomSource(4)), $clock);
        $this->logHandler = new RollAndLogHandler($this->campaigns, $this->journalEntries, $this->rollDice, $clock);
    }

    /** Starts an owned campaign resting on "Scene" and returns its id. */
    public function startedCampaignId(): CampaignId
    {
        $campaignId = CampaignId::generate();
        $this->campaigns->add(Campaign::start(
            $campaignId,
            UserId::fromString('11111111-1111-4111-8111-111111111111'),
            new StagePosition(\App\Shared\Domain\Identifier\GameSystemId::generate(), 'Scene'),
            new \DateTimeImmutable('2026-08-24T09:00:00+00:00'),
        ));

        return $campaignId;
    }
}

/**
 * @internal test fixture
 */
final class InMemoryDiceCampaignRepository implements CampaignRepositoryInterface
{
    /** @var array<string, Campaign> */
    private array $byId = [];

    #[\Override]
    public function add(Campaign $campaign): void
    {
        $this->byId[$campaign->id()->toString()] = $campaign;
    }

    #[\Override]
    public function get(CampaignId $id): ?Campaign
    {
        return $this->byId[$id->toString()] ?? null;
    }

    #[\Override]
    public function delete(CampaignId $id): void
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
}

/**
 * @internal test fixture
 */
final class CountingJournalRepository implements JournalEntryRepositoryInterface
{
    /** @var list<JournalEntry> */
    private array $entries = [];

    #[\Override]
    public function add(JournalEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[\Override]
    public function page(CampaignId $campaignId, ?string $stageName, ?string $cursor, int $limit): \App\Journal\Application\Query\JournalPage
    {
        return new \App\Journal\Application\Query\JournalPage($this->entries, null);
    }

    #[\Override]
    public function deleteAllForCampaign(CampaignId $campaignId): void
    {
        $this->entries = [];
    }

    public function count(): int
    {
        return count($this->entries);
    }
}

/**
 * @internal test fixture — always answers the same face, keeping totals predictable.
 */
final class AlwaysFaceRandomSource implements RandomSourceInterface
{
    public function __construct(private readonly int $value)
    {
    }

    #[\Override]
    public function intBetween(int $min, int $max): int
    {
        if ($this->value < $min || $this->value > $max) {
            throw new \LogicException(sprintf('Fixed value %d outside [%d,%d].', $this->value, $min, $max));
        }

        return $this->value;
    }
}

/**
 * @internal test fixture
 */
final class FrozenTestClock implements ClockInterface
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-24T12:00:00+00:00');
    }
}
