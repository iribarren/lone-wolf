<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\HashingSubject;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * FR-030: the EasyAdmin backoffice is reachable by ROLE_ADMIN accounts
 * through a browser session — unauthenticated visits land on a sign-in
 * form, players are refused, and logout ends the session.
 */
final class AdminBackofficeLoginTest extends WebTestCase
{
    private const PASSWORD = 'correct horse battery';

    private UserRepositoryInterface $users;

    private UserPasswordHasherInterface $hasher;

    /**
     * createClient() boots the kernel exactly once per test; container
     * services are resolved afterwards.
     */
    private function newClient(): KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);
        $this->users = $users;

        $hasher = $container->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        $this->hasher = $hasher;

        return $client;
    }

    public function testUnauthenticatedVisitRedirectsToTheSignInForm(): void
    {
        $client = $this->newClient();

        $client->request('GET', '/admin');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertRouteSame('admin_login');
        self::assertSelectorExists('form input[name="_username"]');
        self::assertSelectorExists('form input[name="_password"]');
        self::assertSelectorExists('form input[name="_csrf_token"]');
    }

    public function testWrongCredentialsStayOnTheFormWithAnError(): void
    {
        $client = $this->newClient();
        $crawler = $client->request('GET', '/admin/login');

        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => 'ghost@example.com',
            '_password' => 'not-the-password',
        ]));
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials');
        self::assertSelectorExists('form input[name="_username"]');
    }

    public function testAdminSignInLandsOnTheBackoffice(): void
    {
        $client = $this->newClient();

        $email = $this->registerUser('admin', [User::ROLE_ADMIN])->email();
        $crawler = $client->request('GET', '/admin/login');

        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => self::PASSWORD,
        ]));

        self::assertResponseRedirects('/admin');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Game systems');
    }

    public function testPlayerRoleCannotReachTheBackoffice(): void
    {
        $client = $this->newClient();

        $email = $this->registerUser('player', [])->email();
        $crawler = $client->request('GET', '/admin/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => self::PASSWORD,
        ]));
        $client->followRedirect();

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLogoutEndsTheAdminSession(): void
    {
        $client = $this->newClient();

        $email = $this->registerUser('logout', [User::ROLE_ADMIN])->email();
        $crawler = $client->request('GET', '/admin/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => self::PASSWORD,
        ]));
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/logout');
        $client->request('GET', '/admin');
        $client->followRedirect();

        self::assertRouteSame('admin_login');
    }

    /**
     * @param list<User::ROLE_*> $extraRoles
     */
    private function registerUser(string $prefix, array $extraRoles): User
    {
        $email = sprintf('%s-%s@integration.test', $prefix, bin2hex(random_bytes(4)));
        $hashed = $this->hasher->hashPassword(
            new HashingSubject($email, [$extraRoles[0] ?? User::ROLE_PLAYER]),
            self::PASSWORD,
        );
        $user = User::register(UserId::generate(), $email, $hashed, $extraRoles);
        $this->users->save($user);

        return $user;
    }
}
