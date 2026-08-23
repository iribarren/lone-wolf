<?php

declare(strict_types=1);

namespace App\Tests\Unit\Oracles;

use App\Oracles\Domain\GlobalScope;
use App\Oracles\Domain\OracleScope;
use App\Oracles\Domain\SystemScope;
use App\Shared\Domain\Identifier\GameSystemId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FR-008/FR-009 visibility predicate matrix: an oracle's scope decides
 * whether it answers to a campaign's game system — global tables answer
 * everywhere, system-scoped tables answer only their own system.
 */
final class OracleScopeTest extends TestCase
{
    private const SYSTEM_A = '6f9619ff-8b86-4d01-b42d-00cf4fc964ff';

    private const SYSTEM_B = '16fd2706-8baf-433b-82eb-8c7fada847da';

    /**
     * @return iterable<string, array{OracleScope, GameSystemId, bool}>
     */
    public static function scopeVisibilityProvider(): iterable
    {
        $systemA = GameSystemId::fromString(self::SYSTEM_A);
        $systemB = GameSystemId::fromString(self::SYSTEM_B);

        yield 'global scope is available to system A' => [new GlobalScope(), $systemA, true];
        yield 'global scope is available to system B' => [new GlobalScope(), $systemB, true];
        yield 'system scope answers its own system' => [new SystemScope($systemA), $systemA, true];
        yield 'system scope refuses a foreign system' => [new SystemScope($systemA), $systemB, false];
    }

    #[DataProvider('scopeVisibilityProvider')]
    public function testVisibilityPredicateMatrix(OracleScope $scope, GameSystemId $campaignSystem, bool $expected): void
    {
        self::assertSame($expected, $scope->isAvailableTo($campaignSystem));
    }

    public function testGlobalScopeFlagsItselfAsGlobal(): void
    {
        self::assertTrue((new GlobalScope())->isGlobal());
    }

    public function testSystemScopeFlagsItselfAsNotGlobal(): void
    {
        self::assertFalse($this->systemAScope()->isGlobal());
    }

    public function testSystemScopeExposesItsBoundSystem(): void
    {
        $systemId = GameSystemId::fromString(self::SYSTEM_A);

        self::assertTrue($this->systemAScope()->systemId()->equals($systemId));
    }

    private function systemAScope(): SystemScope
    {
        return new SystemScope(GameSystemId::fromString(self::SYSTEM_A));
    }
}
