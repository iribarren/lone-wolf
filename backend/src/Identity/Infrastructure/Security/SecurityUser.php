<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User as DomainUser;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Framework adapter exposing the Identity aggregate to Symfony security.
 * Never leaks into Domain (Constitution I).
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public function __construct(private readonly DomainUser $user)
    {
    }

    public function domainUser(): DomainUser
    {
        return $this->user;
    }

    /**
     * @return array<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->user->roles();
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->user->passwordHash();
    }

    #[\Override]
    public function eraseCredentials(): void
    {
        // Nothing sensitive beyond the irreversible hash is held.
    }

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        \assert($this->user->email() !== '');

        return $this->user->email();
    }

    #[\Override]
    public function isEqualTo(UserInterface $other): bool
    {
        return $other instanceof self
            && $other->getUserIdentifier() === $this->getUserIdentifier()
            && $other->getRoles() === $this->getRoles();
    }
}
