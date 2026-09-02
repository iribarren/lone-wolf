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
 * C2: POST /api/auth/login is served by the json_login firewall listener, so
 * API Platform has no resource metadata for it and the generated OpenAPI
 * document carried it under the route NAME `api_auth_login` — a key that is
 * not a path. The endpoint never reached schema.gen.ts, and the frontend had
 * to call the single most security-relevant endpoint through an untyped
 * `apiPath()` cast.
 *
 * LoginPathFactory documents it at its real path instead. These tests pin both
 * halves of that: the document says the right thing, and the firewall — which
 * was deliberately left alone — still answers exactly as documented.
 */
final class LoginContractTest extends WebTestCase
{
    private const PASSWORD = 'correct horse battery';

    public function testTheOpenApiDocumentDescribesTheLoginPath(): void
    {
        $client = static::createClient();
        $document = $this->openApiDocument($client);

        $paths = $document['paths'] ?? null;
        self::assertIsArray($paths);

        self::assertArrayHasKey(
            '/api/auth/login',
            $paths,
            'The login endpoint must be documented at its path.',
        );
        self::assertArrayNotHasKey(
            'api_auth_login',
            $paths,
            'The Lexik route-name key must not leak back into the document.',
        );

        foreach (array_keys($paths) as $key) {
            self::assertStringStartsWith('/', (string) $key, 'Every path key must be a path.');
        }
    }

    public function testTheDocumentedResponseIsTheContractsAuthToken(): void
    {
        $client = static::createClient();
        $document = $this->openApiDocument($client);

        self::assertSame(
            ['$ref' => '#/components/schemas/AuthToken'],
            self::dig($document, ['paths', '/api/auth/login', 'post', 'responses', '200', 'content', 'application/json', 'schema']),
        );

        $authToken = self::dig($document, ['components', 'schemas', 'AuthToken']);
        self::assertIsArray($authToken);
        self::assertSame(['token'], $authToken['required'] ?? null);

        $properties = $authToken['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertArrayHasKey('token', $properties);
        self::assertArrayHasKey('roles', $properties);
    }

    /**
     * Walks a decoded JSON document by key, returning null the moment the path
     * runs out — PHPStan at level max will not chain offsets on `mixed`.
     *
     * @param array<string, mixed> $document
     * @param list<string>         $keys
     */
    private static function dig(array $document, array $keys): mixed
    {
        $cursor = $document;

        foreach ($keys as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return null;
            }

            $cursor = $cursor[$key];
        }

        return $cursor;
    }

    /**
     * The document is only worth anything if the firewall still answers the way
     * it describes — documenting the endpoint must not have moved it.
     */
    public function testTheFirewallStillAnswersAsDocumented(): void
    {
        $client = static::createClient();
        $email = $this->registerPlayer();

        $this->postLogin($client, $email, self::PASSWORD);

        self::assertResponseIsSuccessful();
        $body = $this->decode($client);
        self::assertArrayHasKey('token', $body);
        self::assertIsString($body['token']);
        self::assertArrayHasKey('roles', $body);
        self::assertIsArray($body['roles']);

        $this->postLogin($client, $email, 'not the password');
        self::assertResponseStatusCodeSame(401);
    }

    private function postLogin(KernelBrowser $client, string $email, string $password): void
    {
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            (string) json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function openApiDocument(KernelBrowser $client): array
    {
        $client->request('GET', '/api/docs.json', [], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return $this->decode($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function registerPlayer(): string
    {
        $container = static::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $email = sprintf('login-contract-%s@example.test', bin2hex(random_bytes(4)));
        $users->save(User::register(
            UserId::generate(),
            $email,
            $hasher->hashPassword(new HashingSubject($email, [User::ROLE_PLAYER]), self::PASSWORD),
        ));

        return $email;
    }
}
