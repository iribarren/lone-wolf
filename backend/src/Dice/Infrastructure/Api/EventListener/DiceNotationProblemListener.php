<?php

declare(strict_types=1);

namespace App\Dice\Infrastructure\Api\EventListener;

use App\Dice\Domain\InvalidDiceNotationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maps strict-notation refusals onto RFC 7807 problem+json (contract
 * DiceNotationProblem, FR-027): the typed reason travels in the body so the
 * player learns exactly what was wrong — pre-roll, with no result shown.
 */
final class DiceNotationProblemListener implements EventSubscriberInterface
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
        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, '/api/dice') && !str_starts_with($path, '/api/campaigns/')) {
            return;
        }

        $exception = $event->getThrowable();

        if (!$exception instanceof InvalidDiceNotationException) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'type' => 'https://lonewolf.example/problems/dice-notation',
            'title' => 'Invalid dice notation',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $exception->getMessage(),
            'reason' => $exception->reason()->value,
        ], Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'application/problem+json']));
    }
}
