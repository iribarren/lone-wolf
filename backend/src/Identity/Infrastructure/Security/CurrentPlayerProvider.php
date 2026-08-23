<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Shared-kernel port implementation backed by the security token storage.
 * Keeps Symfony types out of consuming contexts (Constitution I–II).
 */
final readonly class CurrentPlayerProvider implements CurrentUserProviderInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    #[\Override]
    public function currentUserId(): UserId
    {
        $token = $this->tokenStorage->getToken();
        $user = $token === null ? null : $token->getUser();

        if (!$user instanceof SecurityUser) {
            throw new AccessDeniedException('An authenticated player is required.');
        }

        return $user->domainUser()->id();
    }
}
