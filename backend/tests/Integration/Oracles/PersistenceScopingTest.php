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
        $systemA = $this->createSystemNamed('listing-a');
        $systemB = $this->createSystemNamed('listing-b');

        $this->oracles->save($this->globalOracle('Weather'));
        $this->oracles->save($this->scopedOracle('A Encounters', $systemA));
        $this->oracles->save($this->scopedOracle('B Encounters', $systemB));

        $seenByA = $this->titlesVisibleTo($systemA);
        $seenByB = $this->titlesVisibleTo($systemB);

        // FR-009 predicate: global ∪ own-system, nothing else.
        self::assertSame(['Weather'], array_values(array_intersect($seenByA, ['Weather'])));
        self::assertContains('A Encounters', $seenByA);
        self::assertNotContains('B Encounters', $seenByA);

        self::assertContains('Weather', $seenByB);
        self::assertContains('B Encounters', $seenByB);
        self::assertNotContains('A Encounters', $seenByB);
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

        $oracle = $this->scopedOracle('Encounters', $system)
            ->addEntry('Ambush.', 4)
            ->addEntry('Quiet trail.', 1);

        $this->oracles->save($oracle);

        $reloaded = $this->oracles->get($oracle->id());

        self::assertNotNull($reloaded);
        self::assertSame('Encounters', $reloaded->title());
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
