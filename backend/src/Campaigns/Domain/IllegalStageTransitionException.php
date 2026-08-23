<?php

declare(strict_types=1);

namespace App\Campaigns\Domain;

/**
 * FR-016: refusing an illegal move must explain the alternatives. The
 * exception therefore carries every legal next action so the API can render
 * them inside the problem payload.
 */
final class IllegalStageTransitionException extends \DomainException
{
    /**
     * @param list<SuggestedAction> $legalAlternatives
     */
    public function __construct(
        private readonly string $fromStage,
        private readonly string $attemptedStage,
        private readonly array $legalAlternatives,
    ) {
        if ($legalAlternatives === []) {
            parent::__construct(sprintf(
                'Cannot advance from "%s" to "%s": no transition leaves this stage — conclude the campaign instead.',
                $fromStage,
                $attemptedStage,
            ));

            return;
        }

        $names = implode('", "', array_map(
            static fn (SuggestedAction $action): string => (string) $action->toStageName,
            $legalAlternatives,
        ));

        parent::__construct(sprintf(
            'Cannot advance from "%s" to "%s": legal next stages are "%s".',
            $fromStage,
            $attemptedStage,
            $names,
        ));
    }

    public function fromStage(): string
    {
        return $this->fromStage;
    }

    public function attemptedStage(): string
    {
        return $this->attemptedStage;
    }

    /** @return list<SuggestedAction> */
    public function legalAlternatives(): array
    {
        return $this->legalAlternatives;
    }
}
