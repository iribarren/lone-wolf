<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $user = $this->users->findByEmail($identifier);

        if ($user === null) {
            throw new UserNotFoundException(sprintf('No account exists for "%s".', $identifier));
        }

        return new SecurityUser($user);
    }

    /**
     * @param UserInterface $user
     */
    #[\Override]
    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new \InvalidArgumentException('Unsupported user class for refresh.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class || is_subclass_of($class, SecurityUser::class);
    }
}
