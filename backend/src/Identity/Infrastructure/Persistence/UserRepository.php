<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function save(User $user): void
    {
        $repository = $this->entityManager->getRepository(PersistenceUser::class);
        $existing = $repository->find($user->id()->toString());

        if ($existing instanceof PersistenceUser) {
            $existing->mutate($user->roles(), $user->passwordHash());
            $this->entityManager->flush();

            return;
        }

        $this->entityManager->persist(new PersistenceUser(
            $user->id()->toString(),
            $user->email(),
            $user->roles(),
            $user->passwordHash(),
        ));
        $this->entityManager->flush();
    }

    #[\Override]
    public function findByEmail(string $email): ?User
    {
        $record = $this->entityManager
            ->getRepository(PersistenceUser::class)
            ->findOneBy(['email' => strtolower(trim($email))]);

        return $record instanceof PersistenceUser ? $this->toDomain($record) : null;
    }

    #[\Override]
    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    private function toDomain(PersistenceUser $record): User
    {
        return User::reconstitute(
            UserId::fromString($record->id()),
            $record->email(),
            $record->passwordHash(),
            $record->roles(),
        );
    }
}
