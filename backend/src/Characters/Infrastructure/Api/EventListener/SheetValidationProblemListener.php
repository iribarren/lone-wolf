<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Api\EventListener;

use App\Characters\Application\SystemHasNoSheetException;
use App\Characters\Application\CharacterNotFoundException;
use App\Characters\Domain\SheetValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maps character-sheet refusals onto RFC 7807 problem+json (contract
 * SheetValidationProblem, FR-023). Unknown/foreign characters collapse
 * into the same 404 body — existence is never disclosed (FR-019).
 */
final class SheetValidationProblemListener implements EventSubscriberInterface
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
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof SheetValidationException => self::validationProblem($exception),
            $exception instanceof CharacterNotFoundException => self::problem(
                404,
                'Not found',
                'The requested character does not exist.',
            ),
            $exception instanceof SystemHasNoSheetException => self::problem(
                422,
                'No sheet structure',
                $exception->getMessage(),
            ),
            default => null,
        };

        if ($response instanceof JsonResponse) {
            $event->setResponse($response);
        }
    }

    private static function validationProblem(SheetValidationException $exception): JsonResponse
    {
        $violations = array_map(
            static fn ($violation): array => ['field' => $violation->field, 'message' => $violation->message],
            $exception->violations(),
        );

        return new JsonResponse([
            'type' => 'https://lonewolf.example/problems/sheet-validation',
            'title' => 'Sheet validation failed',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $exception->getMessage(),
            'violations' => $violations,
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
