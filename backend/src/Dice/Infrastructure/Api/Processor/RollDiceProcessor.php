<?php

declare(strict_types=1);

namespace App\Dice\Infrastructure\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dice\Application\RollDiceHandler;
use App\Dice\Infrastructure\Api\DiceRollResource;
use App\Dice\Infrastructure\Api\Input\RollDiceInput;

/**
 * POST /api/dice/roll (FR-026..028): one strict parse, one roll. Invalid
 * notation never reaches a die — InvalidDiceNotationException is mapped onto
 * the contract's 422 DiceNotationProblem by DiceNotationProblemListener.
 *
 * @implements ProcessorInterface<RollDiceInput, DiceRollResource>
 */
final readonly class RollDiceProcessor implements ProcessorInterface
{
    public function __construct(private RollDiceHandler $handler)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DiceRollResource
    {
        \assert($data instanceof RollDiceInput);

        return DiceRollResource::fromDomain($this->handler->handle($data->notation));
    }
}
