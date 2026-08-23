<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

/**
 * Response payload of POST /api/auth/register.
 */
final class RegisterOutput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $token = '',
        public array $roles = [],
    ) {
    }
}
