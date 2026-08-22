<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\Identifier\UserId;

/**
 * Account aggregate of the Identity context.
 *
 * Roles follow Symfony conventions (`ROLE_PLAYER`, `ROLE_ADMIN`) because they
 * cross the security boundary; everything else stays framework-free.
 */
final class User
{
    public const ROLE_PLAYER = 'ROLE_PLAYER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    /** @param list<string> $roles */
    private function __construct(
        private readonly UserId $id,
        private string $email,
        private string $passwordHash,
        private array $roles,
    ) {
    }

    /**
     * New accounts always start as players (FR-030).
     *
     * @param list<self::ROLE_*>|array<never>|array<int, string> $extraRoles additional roles granted at provisioning time (admin bootstrap)
     */
    public static function register(UserId $id, string $email, string $passwordHash, array $extraRoles = []): self
    {
        return new self($id, self::normaliseEmail($email), self::assertPasswordHash($passwordHash), [
            ...[self::ROLE_PLAYER],
            ...array_values(array_unique(array_diff($extraRoles, [self::ROLE_PLAYER]))),
        ]);
    }

    /**
     * @param list<string> $roles
     *
     * @internal Used by persistence adapters to rebuild the aggregate.
     */
    public static function reconstitute(UserId $id, string $email, string $passwordHash, array $roles): self
    {
        return new self($id, self::normaliseEmail($email), self::assertPasswordHash($passwordHash), array_values($roles));
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function isAdmin(): bool
    {
        return in_array(self::ROLE_ADMIN, $this->roles, true);
    }

    public function promoteToAdmin(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->roles[] = self::ROLE_ADMIN;
    }

    private static function normaliseEmail(string $email): string
    {
        $normalised = strtolower(trim($email));

        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $email));
        }

        return $normalised;
    }

    private static function assertPasswordHash(string $passwordHash): string
    {
        if (trim($passwordHash) === '') {
            throw new \InvalidArgumentException('Password hash must not be empty.');
        }

        return $passwordHash;
    }
}
