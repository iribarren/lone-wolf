<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Identifier\UserId;

/**
 * Shared-kernel port resolving the authenticated player for HTTP-driven
 * application handlers (FR-019). Implemented by Identity's security bridge;
 * contexts consume the port, never Symfony security types.
 */
interface CurrentUserProviderInterface
{
    public function currentUserId(): UserId;
}
