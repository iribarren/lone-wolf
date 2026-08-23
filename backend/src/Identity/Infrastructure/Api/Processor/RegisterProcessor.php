<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Api\RegisterInput;
use App\Identity\Infrastructure\Api\RegisterOutput;
use App\Identity\Infrastructure\Security\HashingSubject;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Handles POST /api/auth/register (contract Auth paths): validates, hashes,
 * persists, returns a freshly minted JWT with the new player's roles.
 *
 * @template T1 of RegisterInput
 * @implements ProcessorInterface<RegisterInput, RegisterOutput>
 */
final class RegisterProcessor implements ProcessorInterface
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly \Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RegisterOutput
    {
        if (!$data instanceof RegisterInput) {
            throw new \LogicException('Unexpected input for register.');
        }

        $violations = $this->validator->validate($data);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($data, $violations);
        }

        $email = strtolower(trim((string) $data->email));
        $password = (string) $data->password;

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new UnprocessableEntityHttpException('Password must be at least 8 characters long.');
        }

        if ($this->users->findByEmail($email) instanceof User) {
            throw new UnprocessableEntityHttpException('An account with this email already exists.');
        }

        $hashed = $this->passwordHasher->hashPassword(new HashingSubject($email, [User::ROLE_PLAYER]), $password);

        $user = User::register(UserId::generate(), $email, $hashed, [User::ROLE_PLAYER]);
        $this->users->save($user);

        return new RegisterOutput(
            token: $this->jwtManager->create(new SecurityUser($user)),
            roles: $user->roles(),
        );
    }
}
