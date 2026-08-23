<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Emits the contract's AuthToken payload: {token, roles}.
 */
final class JwtSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly JWTTokenManagerInterface $jwtManager)
    {
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Authenticated principal must be a SecurityUser.');
        }

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'roles' => $user->getRoles(),
        ], Response::HTTP_OK);
    }
}
