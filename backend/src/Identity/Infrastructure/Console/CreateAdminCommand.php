<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\HashingSubject;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Provisions the backoffice administrator (FR-030). Credentials come from
 * env/options only — never from committed files.
 */
#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates (or promotes) the ROLE_ADMIN account from ADMIN_EMAIL / ADMIN_PASSWORD.',
)]
final class CreateAdminCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email (defaults to $ADMIN_EMAIL)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password (defaults to $ADMIN_PASSWORD)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?? self::fromEnvironment('ADMIN_EMAIL');
        $password = $input->getOption('password') ?? self::fromEnvironment('ADMIN_PASSWORD');

        if (!is_string($email) || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $io->error('Provide a valid email via --email or $ADMIN_EMAIL.');

            return Command::FAILURE;
        }

        if (!is_string($password) || strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(sprintf('Password must be at least %d characters (--password or $ADMIN_PASSWORD).', self::MIN_PASSWORD_LENGTH));

            return Command::FAILURE;
        }

        $existing = $this->users->findByEmail($email);

        if ($existing instanceof User) {
            $existing->promoteToAdmin();
            $this->users->save($existing);
            $io->success(sprintf('Existing account "%s" now holds ROLE_ADMIN.', $existing->email()));

            return Command::SUCCESS;
        }

        $hashed = $this->passwordHasher->hashPassword(new HashingSubject($email, [User::ROLE_ADMIN]), $password);
        $admin = User::register(UserId::generate(), $email, $hashed, [User::ROLE_ADMIN]);

        $this->users->save($admin);

        $io->success(sprintf('Admin account created: %s', $admin->email()));

        return Command::SUCCESS;
    }

    private static function fromEnvironment(string $name): ?string
    {
        $value = $_SERVER[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
