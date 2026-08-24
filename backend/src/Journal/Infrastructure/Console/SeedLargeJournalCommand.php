<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Console;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Journal\Domain\JournalEntry;
use App\Journal\Domain\JournalEntryKind;
use App\Journal\Infrastructure\Persistence\PersistenceJournalEntry;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\FlowStage;
use App\Rulesets\Domain\FlowTransition;
use App\Rulesets\Domain\GameSystem;
use App\Shared\Domain\ClockInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Performance-evidence fixture for SC-008: fills one campaign's journal with
 * a configurable number of entries (default 500) so the latest-view latency
 * can be asserted by scripts/check-journal-performance.sh.
 *
 * Fixture composition reaches into Identity and Rulesets through their
 * application-layer ports only (see deptrac.yaml fixture edges); campaigns
 * are created through the Campaigns play-loop handler so the starting stage
 * always comes from the real engine path.
 *
 * Re-running against the same player reseeds that campaign deterministically
 * instead of growing unbounded. Entries are built via the domain factory
 * (invariants apply) and bulk-persisted in batches — seeding is an
 * infrastructure concern, so it maps straight onto persistence rows.
 */
#[AsCommand(
    name: 'app:seed:large-journal',
    description: 'Seeds a campaign journal with N entries as the SC-008 performance fixture.',
)]
final class SeedLargeJournalCommand extends Command
{
    private const SYSTEM_NAME = 'Perf Sandbox';
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RulesetRepositoryInterface $rulesets,
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly StartCampaignHandler $startCampaign,
        private readonly JournalEntryRepositoryInterface $journalEntries,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Fixture player email', 'perf@example.com')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Fixture player password (local evidence runs only)', 'perf-player-password')
            ->addOption('entries', null, InputOption::VALUE_REQUIRED, 'Journal entries to seed', '500');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $entriesOption = $input->getOption('entries');

        if (!is_string($email) || !is_string($password) || !is_numeric($entriesOption)) {
            $io->error('--email, --password and --entries must be scalar values.');

            return Command::FAILURE;
        }

        $entries = (int) $entriesOption;

        if ($entries < 1 || $entries > 10_000) {
            $io->error('--entries must be between 1 and 10,000.');

            return Command::FAILURE;
        }

        $player = $this->findOrCreatePlayer($email, $password);
        $systemId = $this->findOrCreatePerfSystem();

        [$campaignId, $stageName] = $this->findOrCreateCampaign($player->id(), $systemId);

        // Reseed deterministically: previous runs never leak into the measured page.
        $this->journalEntries->deleteAllForCampaign($campaignId);
        $persisted = $this->seedEntries($campaignId, $stageName, $entries);

        $io->success(sprintf(
            'Seeded %d journal entries on campaign %s (player %s).',
            $persisted,
            $campaignId->toString(),
            $player->email(),
        ));
        // Machine-readable tail consumed by scripts/check-journal-performance.sh.
        $output->writeln('perf_campaign='.$campaignId->toString());

        return Command::SUCCESS;
    }

    private function findOrCreatePlayer(string $email, string $password): User
    {
        $existing = $this->users->findByEmail($email);

        if ($existing instanceof User) {
            return $existing;
        }

        // Native bcrypt keeps this command free of security-bundle services;
        // the login firewall verifies the hash transparently.
        $player = User::register(UserId::generate(), $email, password_hash($password, PASSWORD_BCRYPT));
        $this->users->save($player);

        return $player;
    }

    private function findOrCreatePerfSystem(): GameSystemId
    {
        $existing = $this->rulesets->findByName(self::SYSTEM_NAME);

        if ($existing instanceof GameSystem) {
            return $existing->id();
        }

        $system = GameSystem::start(
            GameSystemId::generate(),
            self::SYSTEM_NAME,
            'Two-stage sandbox backing the SC-008 journal-performance evidence run.',
            FlowDefinition::create(
                [
                    new FlowStage('Free Play', 'Wander freely; every entry lands on this stage.'),
                    new FlowStage('Wrap Up', 'Terminal stage — close the session whenever you like.'),
                ],
                'Free Play',
                [FlowTransition::fromNames('Free Play', 'Wrap Up')],
            ),
        );

        $this->rulesets->save($system);

        return $system->id();
    }

    /**
     * Reuses the player's existing perf-system campaign or starts a fresh one
     * through the real application handler. Returns [campaignId, stageName].
     *
     * @return array{CampaignId, string}
     */
    private function findOrCreateCampaign(UserId $playerId, GameSystemId $systemId): array
    {
        foreach ($this->campaigns->ownedBy($playerId) as $owned) {
            if ($owned->gameSystemId()->equals($systemId)) {
                return [$owned->id(), $owned->position()->stageName];
            }
        }

        $state = $this->startCampaign->handle(new StartCampaignCommand($playerId, $systemId));

        return [CampaignId::fromString($state->campaignId), $state->currentStage->stageName];
    }

    /**
     * @return int number of rows persisted
     */
    private function seedEntries(CampaignId $campaignId, string $stageName, int $entries): int
    {
        $now = $this->clock->now();
        $persisted = 0;
        $pending = 0;

        for ($i = 0; $i < $entries; ++$i) {
            // Strictly decreasing timestamps exercise the keyset cursor path
            // (same-second collisions included every tenth entry).
            $writtenAt = $now->modify(sprintf('-%d seconds', $entries - $i));
            $entry = JournalEntry::writeNarrative(
                $campaignId,
                $stageName,
                sprintf('[perf fixture %05d] The road continues, indifferent and patient.', $i + 1),
                $writtenAt,
            );

            $this->entityManager->persist(new PersistenceJournalEntry(
                $entry->id()->toString(),
                $entry->campaignId()->toString(),
                $entry->stageName(),
                JournalEntryKind::Narrative,
                $entry->narrative(),
                null,
                null,
                $entry->createdAt(),
            ));

            ++$pending;

            if ($pending === self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $persisted += $pending;
                $pending = 0;
            }
        }

        if ($pending > 0) {
            $this->entityManager->flush();
            $persisted += $pending;
        }

        return $persisted;
    }
}
