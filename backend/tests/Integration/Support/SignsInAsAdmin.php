<?php

declare(strict_types=1);

namespace App\Tests\Integration\Support;

use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\HashingSubject;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Boots a KernelBrowser already signed in through the admin session firewall
 * (Principle V) so backoffice tests can drive the EasyAdmin CRUDs end to end.
 */
trait SignsInAsAdmin
{
    private const ADMIN_PASSWORD = 'correct horse battery';

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $email = sprintf('admin-%s@integration.test', bin2hex(random_bytes(4)));
        $hashed = $hasher->hashPassword(
            new HashingSubject($email, [User::ROLE_ADMIN]),
            self::ADMIN_PASSWORD,
        );
        $users->save(User::register(UserId::generate(), $email, $hashed, [User::ROLE_ADMIN]));

        $crawler = $client->request('GET', '/admin/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => self::ADMIN_PASSWORD,
        ]));
        $client->followRedirect();

        return $client;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function route(string $route, array $parameters = []): string
    {
        $router = static::getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\Generator\UrlGeneratorInterface);

        return $router->generate($route, $parameters);
    }

    /**
     * Raw column text, so "unchanged" means the stored bytes, not a decoded
     * array that would hide key-order or type drift.
     */
    private function storedColumn(string $sql, string $id): string
    {
        $registry = static::getContainer()->get('doctrine');
        \assert($registry instanceof \Doctrine\Persistence\ManagerRegistry);
        $connection = $registry->getConnection();
        \assert($connection instanceof \Doctrine\DBAL\Connection);

        $value = $connection->fetchOne($sql, [$id]);

        return is_string($value) ? $value : '';
    }
}
