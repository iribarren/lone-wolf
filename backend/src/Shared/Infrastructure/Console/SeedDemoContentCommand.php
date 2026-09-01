<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\GlobalScope;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleEntry;
use App\Oracles\Domain\SystemScope;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\FlowStage;
use App\Rulesets\Domain\FlowTransition;
use App\Rulesets\Domain\GameSystem;
use App\Rulesets\Domain\SheetStructure;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Quickstart demo content (FR-031 allows optional seed fixtures): the three
 * showcase systems plus one global and one system-scoped oracle so a fresh
 * stack can walk through validations V1–V6 without backoffice authoring.
 *
 * Flow note: the quickstart describes Freeform Sandbox as a "single stage";
 * the ratified flow invariant mandates at least two named stages (FR-002),
 * so the sandbox ships as Free Play → Reflection with Reflection terminal —
 * the same dead-end-guidance path, expressed within the invariant.
 *
 * Sheet note: two of the three systems ship a character-sheet structure of a
 * deliberately different shape, so the character sheet renders from metadata
 * against something real; Freeform Sandbox ships none, which is the case the
 * player app must explain rather than offer an empty form.
 *
 * Fixture composition reaches into Rulesets and Oracles through their
 * application-layer ports only (see deptrac.yaml fixture edges). The command
 * is idempotent: existing systems/oracle titles are reported and skipped, and
 * a sheet structure is only ever added to a system that has none — an
 * admin's authoring is never overwritten.
 */
#[AsCommand(
    name: 'app:seed:demo',
    description: 'Seeds quickstart demo content: Scene-Sequel, Act Ladder, Freeform Sandbox systems plus global and scoped oracles.',
)]
final class SeedDemoContentCommand extends Command
{
    public function __construct(
        private readonly RulesetRepositoryInterface $rulesets,
        private readonly OracleRepositoryInterface $oracles,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ids = $this->seedSystems($io);
        $this->seedOracles($io, $ids);

        $io->note('Demo content ready — see specs/001-solo-ttrpg-assistant/quickstart.md for the validation walkthrough.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, GameSystemId> system name => id (existing or freshly created)
     */
    private function seedSystems(SymfonyStyle $io): array
    {
        /** @var array<string, GameSystemId> $ids */
        $ids = [];

        foreach ($this->demoSystems() as $name => [$description, $flow, $sheet]) {
            $existing = $this->rulesets->findByName($name);

            if ($existing instanceof GameSystem) {
                $io->text(sprintf('system  skip   "%s" already exists', $name));
                $ids[$name] = $existing->id();
                $this->backfillSheet($io, $existing, $sheet);

                continue;
            }

            $system = GameSystem::start(GameSystemId::generate(), $name, $description, $flow);

            if ($sheet !== null) {
                $system = $system->withSheetStructure(SheetStructure::create($sheet));
            }

            $this->rulesets->save($system);
            $io->text(sprintf(
                'system  create "%s" (%d stages, %d sheet fields)',
                $name,
                count($flow->stages()),
                count($sheet ?? []),
            ));
            $ids[$name] = $system->id();
        }

        return $ids;
    }

    /**
     * Gives a pre-existing demo system the sheet it was seeded without —
     * older stacks were seeded before the shapes existed. A system that
     * already carries a structure is left exactly as its admin authored it.
     *
     * @param list<FieldDefinition>|null $sheet
     */
    private function backfillSheet(SymfonyStyle $io, GameSystem $system, ?array $sheet): void
    {
        if ($sheet === null || $system->sheetStructure() instanceof SheetStructure) {
            return;
        }

        $this->rulesets->save($system->withSheetStructure(SheetStructure::create($sheet)));
        $io->text(sprintf('sheet   create "%s" (%d fields)', $system->name(), count($sheet)));
    }

    /**
     * @return array<string, array{string, FlowDefinition, list<FieldDefinition>|null}>
     */
    private function demoSystems(): array
    {
        return [
            'Scene-Sequel Demo' => [
                'Two-pulse loop: open a Scene pursuing an intent, then run the Sequel.',
                FlowDefinition::create(
                    [
                        new FlowStage('Setup', 'Set the stage: frame where your character is and what they want.'),
                        new FlowStage('Scene', 'Open your Scene: pursue your intent until it resolves or twists.'),
                        new FlowStage('Sequel', 'Run your Sequel: react, recover, and steer toward the next Scene.'),
                    ],
                    'Scene',
                    [
                        new FlowTransition('Setup', 'Scene'),
                        new FlowTransition('Scene', 'Sequel'),
                        new FlowTransition('Sequel', 'Setup'),
                    ],
                ),
                [FieldDefinition::number('hp', 'Hit points', requiredForPc: true, requiredForNpc: false)],
            ],
            'Act Ladder' => [
                'Three-rung escalation: climb from the first act through beats into resolution.',
                FlowDefinition::create(
                    [
                        new FlowStage('Act I', "Climb the ladder: escalate the act's central conflict."),
                        new FlowStage('Beat', 'Play the Beat: one focused exchange that moves the act forward.'),
                        new FlowStage('Act II', 'Resolve the act: pay the costs and close its threads.'),
                    ],
                    'Act I',
                    [
                        new FlowTransition('Act I', 'Beat'),
                        new FlowTransition('Beat', 'Act II'),
                    ],
                ),
                [
                    FieldDefinition::number('willpower', 'Willpower', requiredForPc: true, requiredForNpc: false),
                    FieldDefinition::text('discipline', 'Discipline', requiredForPc: true, requiredForNpc: true),
                ],
            ],
            'Freeform Sandbox' => [
                'No prescribed loop: wander freely until you choose to wrap the session.',
                FlowDefinition::create(
                    [
                        new FlowStage('Free Play', 'Wander freely: follow any thread — nothing is required here.'),
                        new FlowStage('Reflection', 'Dead end reached: wrap this thread in your own words, then rest.'),
                    ],
                    'Free Play',
                    [new FlowTransition('Free Play', 'Reflection')],
                ),
                // Deliberately sheetless: the app must explain the absence.
                null,
            ],
        ];
    }

    /**
     * @param array<string, GameSystemId> $ids
     */
    private function seedOracles(SymfonyStyle $io, array $ids): void
    {
        $ladderId = $ids['Act Ladder'] ?? null;

        if (!$ladderId instanceof GameSystemId) {
            $io->warning('Act Ladder system missing — skipping oracle seeding.');

            return;
        }

        // Titles visible to either demo system cover every demo table
        // (global rows answer visibleTo for any system).
        $knownTitles = [];
        foreach ($this->oracles->visibleTo($ladderId) as $oracle) {
            $knownTitles[$oracle->title()] = true;
        }
        foreach ($this->oracles->visibleTo($ids['Scene-Sequel Demo']) as $oracle) {
            $knownTitles[$oracle->title()] = true;
        }

        foreach ($this->demoOracles($ladderId) as $oracle) {
            if (isset($knownTitles[$oracle->title()])) {
                $io->text(sprintf('oracle  skip   "%s" already exists', $oracle->title()));

                continue;
            }

            $this->oracles->save($oracle);
            $scope = $oracle->scope()->isGlobal() ? 'global' : 'scoped';
            $io->text(sprintf(
                'oracle  create "%s" (%s, %d entries)',
                $oracle->title(),
                $scope,
                $oracle->entryCount(),
            ));
        }
    }

    /**
     * @return list<Oracle>
     */
    private function demoOracles(GameSystemId $ladderId): array
    {
        return [
            Oracle::start(
                OracleId::generate(),
                'Generic Weather',
                new GlobalScope(),
                [
                    OracleEntry::place('Clear skies and a light, honest wind.', 3),
                    OracleEntry::place('Overcast; the day keeps its own counsel.', 2),
                    OracleEntry::place('Sudden rain hammers the road ahead.', 2),
                    OracleEntry::place('A rolling storm swallows the horizon.', 1),
                ],
            ),
            Oracle::start(
                OracleId::generate(),
                'Ladder Encounters',
                new SystemScope($ladderId),
                [
                    OracleEntry::place('An ambush waits where the path narrows.', 2),
                    OracleEntry::place('Wary travellers trade news for silence.', 2),
                    OracleEntry::place('Signs of a beast moving just below sight.', 1),
                ],
            ),
        ];
    }
}
