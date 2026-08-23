<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;

/**
 * POST /api/auth/register — public account creation returning a fresh token
 * (contract: Auth paths). Modeled as an API Platform resource so the endpoint
 * stays part of the generated OpenAPI contract.
 */
#[ApiResource(
    shortName: 'AuthRegister',
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            security: "true",
            input: self::class,
            output: RegisterOutput::class,
            processor: Processor\RegisterProcessor::class,
        ),
    ],
)]
final class RegisterInput
{
    public function __construct(
        public readonly ?string $email = null,
        public readonly ?string $password = null,
    ) {
    }
}
