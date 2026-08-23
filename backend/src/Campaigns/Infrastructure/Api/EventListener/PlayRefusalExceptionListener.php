<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Api\EventListener;

use App\Campaigns\Application\ConfirmationRequiredException;
use App\Campaigns\Application\SystemNotPlayableException;
use App\Campaigns\Domain\CampaignAccessDeniedException;
use App\Campaigns\Domain\CampaignNotFoundException;
use App\Campaigns\Domain\IllegalStageTransitionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maps guided-play domain refusals onto RFC 7807 problem+json responses
 * (contract /advance 422, FR-016/019/020). Unknown and foreign campaigns
 * collapse into the same 404 body — existence is never disclosed.
 */
final class PlayRefusalExceptionListener implements EventSubscriberInterface
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 8]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof IllegalStageTransitionException => self::illegalTransition($exception),
            $exception instanceof SystemNotPlayableException => self::problem(422, 'Game system not playable', $exception->getMessage()),
            $exception instanceof ConfirmationRequiredException => self::problem(400, 'Confirmation required', $exception->getMessage()),
            // Identical body for unknown vs foreign campaigns (FR-019).
            $exception instanceof CampaignNotFoundException, $exception instanceof CampaignAccessDeniedException => self::problem(404, 'Not found', 'The requested campaign does not exist.'),
            default => null,
        };

        if ($response instanceof JsonResponse) {
            $event->setResponse($response);
        }
    }

    private static function illegalTransition(IllegalStageTransitionException $exception): JsonResponse
    {
        $alternatives = [];

        foreach ($exception->legalAlternatives() as $action) {
            $alternatives[] = [
                'kind' => strtolower($action->kind->name),
                'toStageId' => $action->toStageName,
                'toStageName' => $action->toStageName,
                'prompt' => $action->prompt,
            ];
        }

        return new JsonResponse([
            'type' => 'https://lonewolf.example/problems/illegal-stage-transition',
            'title' => 'Illegal stage transition',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $exception->getMessage(),
            'legalAlternatives' => $alternatives,
        ], Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'application/problem+json']);
    }

    private static function problem(int $status, string $title, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type' => sprintf('https://lonewolf.example/problems/%s', strtolower(str_replace(' ', '-', $title))),
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
