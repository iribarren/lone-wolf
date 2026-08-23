<?php

declare(strict_types=1);

namespace App\Tests\Integration\Oracles;

use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\GlobalScope;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleEntry;
use App\Oracles\Domain\SystemScope;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence contract for oracle scoping (US3 / FR-008/FR-009):
 *
 * - the scoped listing query answers global ∪ own-system rows and never
 *   leaks another system's tables;
 * - the partial unique index on (scope_system_id) WHERE scope_type='system'
 *   guards system-scope integrity at the storage boundary.
 */
final class PersistenceScopingTest extends KernelTestCase
{
    private CreateGameSystemHandler $createSystem;

    private OracleRepositoryInterface $oracles;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $createSystem = $container->get(CreateGameSystemHandler::class);
        $oracles = $container->get(OracleRepositoryInterface::class);

        \assert($createSystem instanceof CreateGameSystemHandler);
        \assert($oracles instanceof OracleRepositoryInterface);

        $this->createSystem = $createSystem;
        $this->oracles = $oracles;
    }

    public function testScopedListingReturnsGlobalUnionOwnSystemRows(): void
    {
        $weather = $this->uniqueTitle('Weather');
        $encountersA = $this->uniqueTitle('A Encounters');
        $encountersB = $this->uniqueTitle('B Encounters');

        $systemA = $this->createSystemNamed('listing-a');
        $systemB = $this->createSystemNamed('listing-b');

        $this->oracles->save($this->globalOracle($weather));
        $this->oracles->save($this->scopedOracle($encountersA, $systemA));
        $this->oracles->save($this->scopedOracle($encountersB, $systemB));

        $seenByA = $this->titlesVisibleTo($systemA);
        $seenByB = $this->titlesVisibleTo($systemB);

        // FR-009 predicate: global ∪ own-system, nothing else.
        self::assertContains($weather, $seenByA);
        self::assertContains($encountersA, $seenByA);
        self::assertNotContains($encountersB, $seenByA);

        self::assertContains($weather, $seenByB);
        self::assertContains($encountersB, $seenByB);
        self::assertNotContains($encountersA, $seenByB);
    }

    public function testPartialUniqueIndexEnforcesSystemScopeIntegrity(): void
    {
        $system = $this->createSystemNamed('unique-scope');

        $this->oracles->save($this->scopedOracle('First table', $system));

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->oracles->save($this->scopedOracle('Second table', $system));
    }

    public function testRoundTripPreservesScopeAndWeightedEntries(): void
    {
        $system = $this->createSystemNamed('round-trip');

        $oracle = $this->scopedOracle($this->uniqueTitle('Encounters'), $system)
            ->addEntry('Ambush.', 4)
            ->addEntry('Quiet trail.', 1);

        $this->oracles->save($oracle);

        $reloaded = $this->oracles->get($oracle->id());

        self::assertNotNull($reloaded);
        self::assertSame('Encounters', substr($reloaded->title(), 0, 10));
        self::assertTrue($reloaded->scope() instanceof SystemScope);
        self::assertTrue($reloaded->scope()->isAvailableTo($system));
        self::assertFalse($reloaded->scope()->isAvailableTo(GameSystemId::generate()));
        self::assertSame(
            [['text' => 'Ambush.', 'weight' => 4], ['text' => 'Quiet trail.', 'weight' => 1]],
            array_map(
                static fn (OracleEntry $entry): array => ['text' => $entry->text(), 'weight' => $entry->weight()],
                $reloaded->entries(),
            ),
        );
    }

    private function createSystemNamed(string $prefix): GameSystemId
    {
        return $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('%s-%s', $prefix, bin2hex(random_bytes(4))),
            description: 'Oracle scoping fixture.',
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [],
        ));
    }

    private function globalOracle(string $title): Oracle
    {
        return Oracle::start(OracleId::generate(), $title, new GlobalScope());
    }

    /**
     * Integration storage persists across runs — unique suffixes keep
     * fixtures collision-free.
     */
    private function uniqueTitle(string $base): string
    {
        return sprintf('%s-%s', $base, bin2hex(random_bytes(3)));
    }

    private function scopedOracle(string $title, GameSystemId $systemId): Oracle
    {
        return Oracle::start(OracleId::generate(), $title, new SystemScope($systemId));
    }

    /**
     * @param GameSystemId $systemId
     * @return list<string>
     */
    private function titlesVisibleTo(GameSystemId $systemId): array
    {
        return array_map(
            static fn (Oracle $oracle): string => $oracle->title(),
            $this->oracles->visibleTo($systemId),
        );
    }
}
