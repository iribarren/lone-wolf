<?php

declare(strict_types=1);

namespace App\Rulesets\Application;

use App\Rulesets\Application\Command\SetSystemStatusCommand;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;

/**
 * FR-001/FR-006: toggling availability for NEW campaigns never mutates the
 * definition data existing campaigns rely on.
 */
final class SetSystemStatusHandler
{
    public function __construct(private readonly RulesetRepositoryInterface $systems)
    {
    }

    public function handle(SetSystemStatusCommand $command): void
    {
        $system = $this->systems->get($command->systemId);

        if ($system === null) {
            throw new \InvalidArgumentException('Game system not found.');
        }

        $this->systems->save($command->active ? $system->activate() : $system->deactivate());
    }
}
