<?php

declare(strict_types=1);

namespace App\Rulesets\Application;

use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Domain\GameSystem;
use App\Shared\Domain\Identifier\GameSystemId;

final class CreateGameSystemHandler
{
    public function __construct(private readonly RulesetRepositoryInterface $systems)
    {
    }

    public function handle(CreateGameSystemCommand $command): GameSystemId
    {
        $existing = $this->systems->findByName($command->name);
        if ($existing instanceof GameSystem) {
            throw new \InvalidArgumentException(sprintf('A game system named "%s" already exists.', $command->name));
        }

        $id = GameSystemId::generate();

        $this->systems->save(GameSystem::start(
            $id,
            $command->name,
            $command->description,
            FlowFactory::fromPayload($command->stageNames, $command->startingStage, $command->transitions),
        ));

        return $id;
    }
}
