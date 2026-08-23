<?php

declare(strict_types=1);

namespace App\Tests\Unit\Oracles;

use App\Oracles\Domain\GlobalScope;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleEntry;
use App\Oracles\Domain\SystemScope;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleEntryId;
use App\Shared\Domain\Identifier\OracleId;
use PHPUnit\Framework\TestCase;

/**
 * Oracle aggregate invariants (FR-007/FR-008): weighted entries carry
 * strictly positive weights, titles are present and bounded, entries may
 * rest empty (the friendly empty-table path), and aggregate visibility
 * follows the scope strategy.
 */
final class OracleAggregateTest extends TestCase
{
    private const SCOPED_SYSTEM = '6f9619ff-8b86-4d01-b42d-00cf4fc964ff';

    private const OTHER_SYSTEM = '16fd2706-8baf-433b-82eb-8c7fada847da';

    public function testZeroWeightIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positive');

        OracleEntry::place('The road forks without warning.', 0);
    }

    public function testNegativeWeightIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positive');

        OracleEntry::place('A stranger watches from the treeline.', -3);
    }

    public function testBlankEntryTextIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty');

        OracleEntry::place('   ', 2);
    }

    public function testOverlongEntryTextIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('500');

        OracleEntry::place(str_repeat('x', 501), 1);
    }

    public function testValidEntryKeepsIdentityTextAndWeight(): void
    {
        $entry = OracleEntry::place('Yes, but at a cost.', 3);

        self::assertSame('Yes, but at a cost.', $entry->text());
        self::assertSame(3, $entry->weight());

        $id = OracleEntryId::generate();
        $restored = OracleEntry::reconstitute($id, 'No, and the situation worsens.', 1);

        self::assertTrue($restored->id()->equals($id));
    }

    public function testBlankTitleIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty');

        $this->globalOracle(title: '  ');
    }

    public function testOverlongTitleIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('160');

        $this->globalOracle(title: str_repeat('t', 161));
    }

    public function testNewOracleMayRestEmptyForTheFriendlyNoticePath(): void
    {
        $oracle = $this->globalOracle();

        self::assertSame(0, $oracle->entryCount());
        self::assertSame([], $oracle->entries());
    }

    public function testAddedEntriesKeepAuthoringOrderAndWeights(): void
    {
        $oracle = $this->globalOracle()
            ->addEntry('Yes.', 5)
            ->addEntry('No.', 2);

        self::assertSame(2, $oracle->entryCount());
        self::assertSame(['Yes.', 'No.'], array_map(static fn (OracleEntry $e): string => $e->text(), $oracle->entries()));
        self::assertSame([5, 2], array_map(static fn (OracleEntry $e): int => $e->weight(), $oracle->entries()));
    }

    public function testWithEntriesReplacesTheWholeSetForReweightEdits(): void
    {
        $oracle = $this->globalOracle()->addEntry('Yes.', 5);

        $reweighted = $oracle->withEntries([
            OracleEntry::place('Maybe.', 4),
            OracleEntry::place('Never.', 1),
        ]);

        self::assertSame(['Maybe.', 'Never.'], array_map(static fn (OracleEntry $e): string => $e->text(), $reweighted->entries()));
        self::assertSame(2, $reweighted->entryCount());
    }

    public function testAggregateVisibilityFollowsTheScopeStrategy(): void
    {
        $own = GameSystemId::fromString(self::SCOPED_SYSTEM);
        $foreign = GameSystemId::fromString(self::OTHER_SYSTEM);

        $global = $this->globalOracle();
        $scoped = $this->scopedTo($own);

        // FR-009 at aggregate level: global answers everywhere, scoped only home.
        self::assertTrue($global->isAvailableTo($own));
        self::assertTrue($global->isAvailableTo($foreign));
        self::assertTrue($scoped->isAvailableTo($own));
        self::assertFalse($scoped->isAvailableTo($foreign));
    }

    private function globalOracle(string $title = 'Generic Weather'): Oracle
    {
        return Oracle::start(OracleId::generate(), $title, new GlobalScope());
    }

    private function scopedTo(GameSystemId $systemId): Oracle
    {
        return Oracle::start(
            OracleId::generate(),
            'Act Ladder Encounters',
            new SystemScope($systemId),
        );
    }
}
