<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dice;

use App\Dice\Domain\DiceNotation;
use App\Dice\Domain\DiceRoll;
use App\Dice\Domain\DiceRoller;
use App\Shared\Domain\ClockInterface;
use App\Shared\Domain\RandomSourceInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * US6 roller (T087): every batch roll must be mathematically correct —
 * diceValues length = N, each value within [1..M], total = Σ ± modifier
 * (FR-028) — and the 100 %-valid / 100 %-refused gate (SC-005) leans on
 * these pure, seeded determinism guarantees.
 */
final class DiceRollerTest extends TestCase
{
    private const BATCH_SIZE = 500;

    /**
     */
    #[DataProvider('provideNotations')]
    public function testBatchRollsAreMathematicallyCorrect(string $input, int $expectedCount, int $faces, int $modifier): void
    {
        $roller = new DiceRoller(new SeededRandomSource(20260824));
        $notation = DiceNotation::fromString($input);

        foreach (range(1, self::BATCH_SIZE) as $batchIndex) {
            $roll = $roller->roll($notation, new FrozenClock());

            self::assertCount($expectedCount, $roll->diceValues(), sprintf('%s: die count mismatch.', $input));

            foreach ($roll->diceValues() as $dieValue) {
                self::assertGreaterThanOrEqual(1, $dieValue, sprintf('%s: a die fell below 1.', $input));
                self::assertLessThanOrEqual($faces, $dieValue, sprintf('%s: a die exceeded its faces.', $input));
            }

            self::assertSame($modifier, $roll->modifier());
            self::assertSame(
                array_sum($roll->diceValues()) + $modifier,
                $roll->total(),
                sprintf('%s: total is not Σ diceValues ± modifier.', $input),
            );
        }
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function provideNotations(): iterable
    {
        yield '2d6' => ['2d6', 2, 6, 0];
        yield '1d20+5' => ['1d20+5', 1, 20, 5];
        yield '3d6-2' => ['3d6-2', 3, 6, -2];
        yield '50d1000+10000' => ['50d1000+10000', 50, 1000, 10_000];
    }

    public function testSeededRunsAreReproducible(): void
    {
        $notation = DiceNotation::fromString('4d10+3');

        $first = (new DiceRoller(new SeededRandomSource(42)))->roll($notation, new FrozenClock());
        $second = (new DiceRoller(new SeededRandomSource(42)))->roll($notation, new FrozenClock());

        self::assertSame($first->diceValues(), $second->diceValues());
        self::assertSame($first->total(), $second->total());
    }

    public function testRollCarriesTheClockTimestamp(): void
    {
        $roller = new DiceRoller(new SeededRandomSource(7));
        $clock = new FrozenClock();

        $roll = $roller->roll(DiceNotation::fromString('2d6'), $clock);

        self::assertSame($clock->now(), $roll->rolledAt());
    }

    public function testCanonicalNotationSurvivesTheRoundTrip(): void
    {
        $roller = new DiceRoller(new SeededRandomSource(7));

        $roll = $roller->roll(DiceNotation::fromString('1d20 + 5'), new FrozenClock());

        self::assertSame('1d20+5', $roll->notation()->toString());
        self::assertInstanceOf(DiceRoll::class, $roll);
    }
}

/**
 * Deterministic collaborator standing in for production randomness
 * (Constitution IV): seeded Mersenne Twister, same engine family the
 * oracle selector tests rely on.
 */
final class SeededRandomSource implements RandomSourceInterface
{
    private Randomizer $randomizer;

    public function __construct(int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    #[\Override]
    public function intBetween(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }
}

final class FrozenClock implements ClockInterface
{
    private \DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new \DateTimeImmutable('2026-08-24T12:00:00+00:00');
    }

    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
