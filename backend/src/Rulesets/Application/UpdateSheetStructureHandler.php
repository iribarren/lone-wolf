<?php

declare(strict_types=1);

namespace App\Rulesets\Application;

use App\Rulesets\Application\Command\UpdateSheetStructureCommand;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Domain\SheetStructure;

/**
 * Replaces the system's sheet structure; the version stamp inside the value
 * object is bumped by SheetStructure::withFields (FR-022/FR-025).
 */
final class UpdateSheetStructureHandler
{
    public function __construct(private readonly RulesetRepositoryInterface $systems)
    {
    }

    public function handle(UpdateSheetStructureCommand $command): void
    {
        $system = $this->systems->get($command->systemId);

        if ($system === null) {
            throw new \InvalidArgumentException('Game system not found.');
        }

        $current = $system->sheetStructure();

        $updated = $current === null
            ? SheetStructure::create($command->fields)
            : $current->withFields($command->fields);

        $this->systems->save($system->withSheetStructure($updated));
    }
}
