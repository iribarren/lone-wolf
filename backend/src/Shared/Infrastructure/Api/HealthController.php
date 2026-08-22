<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/health — public liveness probe (T021, contract tooling + quickstart).
 */
#[Route('/api/health', name: 'api_health', methods: ['GET'])]
final readonly class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
