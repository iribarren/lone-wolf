<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dice;

use App\Dice\Domain\DiceNotation;
use App\Dice\Domain\DiceNotationFailureReason;
use App\Dice\Domain\InvalidDiceNotationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * US6 parser matrix (T086): strict NdM±K parsing with typed, pre-roll
 * failure reasons (FR-026/FR-027, Edge Case §5) — bounds N ∈ [1,50],
 * M ∈ [2,1000], K ∈ [-10000,10000] (data-model.md Dice Context).
 */
final class DiceNotationParserTest extends TestCase
{
    /**
     */
    #[DataProvider('provideValidNotations')]
    public function testParsesValidNotation(string $input, int $expectedCount, int $expectedFaces, int $expectedModifier, string $canonical): void
    {
        $notation = DiceNotation::fromString($input);

        self::assertSame($expectedCount, $notation->count());
        self::assertSame($expectedFaces, $notation->faces());
        self::assertSame($expectedModifier, $notation->modifier());
        self::assertSame($canonical, $notation->toString());
    }

    /**
     * @return iterable<string, array{string, int, int, int, string}>
     */
    public static function provideValidNotations(): iterable
    {
        yield 'plain 2d6' => ['2d6', 2, 6, 0, '2d6'];
        yield 'positive modifier' => ['1d20+5', 1, 20, 5, '1d20+5'];
        yield 'negative modifier' => ['3d6-2', 3, 6, -2, '3d6-2'];
        yield 'whitespace around sign' => ['2d6 + 3', 2, 6, 3, '2d6+3'];
        yield 'boundary dice' => ['50d1000-10000', 50, 1000, -10_000, '50d1000-10000'];
    }

    /**
     */
    #[DataProvider('provideMalformedNotations')]
    public function testMalformedSyntaxIsRefusedPreRoll(string $input): void
    {
        try {
            DiceNotation::fromString($input);

            self::fail(sprintf('"%s" should have been refused as malformed.', $input));
        } catch (InvalidDiceNotationException $exception) {
            self::assertSame(DiceNotationFailureReason::Malformed, $exception->reason());
            self::assertSame('malformed', $exception->reason()->value);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMalformedNotations(): iterable
    {
        yield 'missing faces' => ['2d'];
        yield 'trailing garbage' => ['d20x'];
        yield 'countless die' => ['d20'];
        yield 'empty input' => [''];
        yield 'not dice at all' => ['attack roll'];
        yield 'two modifiers' => ['2d6+3-1'];
        yield 'uppercase d is not standard notation' => ['2D6'];
        yield 'fractional faces' => ['1d6.5'];
    }

    public function testZeroCountIsSpecificallyInvalidCount(): void
    {
        try {
            DiceNotation::fromString('0d6');

            self::fail('"0d6" should have been refused.');
        } catch (InvalidDiceNotationException $exception) {
            self::assertSame(DiceNotationFailureReason::InvalidCount, $exception->reason());
        }
    }

    /**
     */
    #[DataProvider('provideInvalidFaces')]
    public function testFacesBelowTwoAreSpecificallyInvalidFaces(string $input): void
    {
        try {
            DiceNotation::fromString($input);

            self::fail(sprintf('"%s" should have been refused for its faces.', $input));
        } catch (InvalidDiceNotationException $exception) {
            self::assertSame(DiceNotationFailureReason::InvalidFaces, $exception->reason());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFaces(): iterable
    {
        yield 'zero faces' => ['1d0'];
        yield 'single face' => ['4d1'];
    }

    /**
     */
    #[DataProvider('provideOutOfBoundsNotations')]
    public function testBeyondBoundsIsRefusedAsOutOfBounds(string $input): void
    {
        try {
            DiceNotation::fromString($input);

            self::fail(sprintf('"%s" should have been refused as out of bounds.', $input));
        } catch (InvalidDiceNotationException $exception) {
            self::assertSame(DiceNotationFailureReason::OutOfBounds, $exception->reason());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOutOfBoundsNotations(): iterable
    {
        yield 'count above fifty' => ['51d6'];
        yield 'faces above one thousand' => ['1d1001'];
        yield 'modifier above ten thousand' => ['1d20+10001'];
        yield 'modifier below minus ten thousand' => ['1d20-10001'];
        yield 'absurd digit run' => ['999999999999d6'];
    }
}
