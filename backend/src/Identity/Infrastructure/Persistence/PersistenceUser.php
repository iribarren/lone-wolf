<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

/**
 * Persistence model for the Identity context. Kept separate from the Domain
 * aggregate so Doctrine never leaks into Domain (Constitution I); the
 * repository maps between the two shapes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
class PersistenceUser
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    /** @var array<string> */
    #[ORM\Column(type: 'jsonb')]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordHash;

    /**
     * @param array<string> $roles
     */
    public function __construct(string $id, string $email, array $roles, string $passwordHash)
    {
        $this->id = $id;
        $this->email = $email;
        $this->roles = $roles;
        $this->passwordHash = $passwordHash;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_values($this->roles);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /** @param array<string> $roles */
    public function mutate(array $roles, string $passwordHash): void
    {
        $this->roles = $roles;
        $this->passwordHash = $passwordHash;
    }
}
