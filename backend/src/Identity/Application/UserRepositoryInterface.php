<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\User;

/**
 * Port owned by the Identity application layer; implemented by Doctrine in
 * Infrastructure (Constitution I).
 */
interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function emailExists(string $email): bool;
}
