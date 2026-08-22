<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\User;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegistrationGrantsPlayerRoleAndNormalisesEmail(): void
    {
        $user = User::register(UserId::generate(), '  Player@Example.COM ', '$2y$10$hash');

        self::assertSame('player@example.com', $user->email());
        self::assertSame([User::ROLE_PLAYER], $user->roles());
        self::assertFalse($user->isAdmin());
    }

    public function testAdminProvisioningKeepsRolesUnique(): void
    {
        $user = User::register(UserId::generate(), 'admin@example.com', '$2y$10$hash', [User::ROLE_ADMIN, User::ROLE_PLAYER]);

        self::assertSame([User::ROLE_PLAYER, User::ROLE_ADMIN], $user->roles());
        self::assertTrue($user->isAdmin());
    }

    public function testPromoteToAdminIsIdempotent(): void
    {
        $user = User::register(UserId::generate(), 'p@example.com', '$2y$10$hash');

        $user->promoteToAdmin();
        $user->promoteToAdmin();

        self::assertSame([User::ROLE_PLAYER, User::ROLE_ADMIN], $user->roles());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::register(UserId::generate(), 'definitely-not-an-email', '$2y$10$hash');
    }

    public function testRejectsEmptyPasswordHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        User::register(UserId::generate(), 'p@example.com', '   ');
    }
}
