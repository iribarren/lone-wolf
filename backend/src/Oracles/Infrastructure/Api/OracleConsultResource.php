<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Api;

use App\Oracles\Application\ConsultOracleHandler;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\OracleId;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Oracle consultation endpoint.
 *
 * POST /api/campaigns/{campaignId}/oracles/{oracleId}/consult
 *
 * Consult an oracle — exactly one weighted-random result (FR-010)
 * or friendly empty-table notice (FR-011).
 *
 * Responses:
 *   200: Single selection or empty/unavailable notice (ConsultationOutcome)
 *   404: Oracle not found or campaign not found
 */
#[ApiResource(
    uriTemplate: '/campaigns/{campaignId}/oracles/{oracleId}/consult',
    operations: [],
    normalizationContext: ['groups' => ['oracle:read']],
)]
final class OracleConsultResource
{
    public function __construct(
        private ConsultOracleHandler $consultHandler,
    ) {
    }

    public function handle(Request $request, CampaignId $campaignId, OracleId $oracleId, JsonResponse $response): JsonResponse
    {
        $outcome = $this->consultHandler->handle($campaignId, $oracleId);

        if ($outcome->isUnavailable()) {
            return $response->json([
                'type' => $outcome->reason(),
            ], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'type' => $outcome->isSelected() ? 'selected' : 'empty_table',
        ];

        if ($outcome->isSelected()) {
            $data['selected'] = [
                'text' => $outcome->selected()->text(),
                'weight' => $outcome->selected()->weight(),
            ];
        } elseif ($outcome->isEmptyTable()) {
            $data['emptyTable'] = true;
        } else {
            $data['unavailable'] = $outcome->reason();
        }

        return $response->json($data, Response::HTTP_OK);
    }
}