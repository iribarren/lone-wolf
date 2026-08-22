<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\HashingSubject;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Identifier\UserId;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/auth/register — contract path (201 AuthToken | 422 problem+json).
 */
#[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
final readonly class RegisterController
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private UserRepositoryInterface $users,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->problem('Malformed JSON body.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email = isset($payload['email']) && is_string($payload['email']) ? trim($payload['email']) : '';
        $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->problem('A valid email is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return $this->problem(
                sprintf('Password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->users->emailExists($email)) {
            return $this->problem('An account with this email already exists.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hashed = $this->passwordHasher->hashPassword(new HashingSubject(strtolower($email), [User::ROLE_PLAYER]), $password);
        $user = User::register(UserId::generate(), $email, $hashed);

        $this->users->save($user);

        return new JsonResponse([
            'token' => $this->jwtManager->create(new SecurityUser($user)),
            'roles' => $user->roles(),
        ], Response::HTTP_CREATED);
    }

    private function problem(string $detail, int $status): JsonResponse
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'title' => 'Unprocessable Entity',
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
