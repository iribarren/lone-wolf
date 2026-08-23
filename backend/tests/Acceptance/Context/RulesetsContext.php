<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Application\Query\ListAvailableSystemsQuery;
use App\Rulesets\Application\UpdateFlowDefinitionHandler;
use App\Rulesets\Domain\GameSystem;
use App\Rulesets\Infrastructure\Persistence\InMemoryStageOccupancyChecker;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use PHPUnit\Framework\AssertionFailedError;

final class RulesetsContext implements Context
{
    private ?string $refusalMessage = null;

    public function __construct(
        private readonly CreateGameSystemHandler $createHandler,
        private readonly UpdateFlowDefinitionHandler $updateHandler,
        private readonly RulesetRepositoryInterface $systems,
        private readonly ListAvailableSystemsQuery $listAvailable,
        private readonly InMemoryStageOccupancyChecker $occupancy,
    ) {
    }

    /**
     * @Given an authenticated admin
     */
    public function authenticatedAdmin(): void
    {
        // Application handlers are invoked in-process with backoffice
        // privileges; JWT transport is covered by the API plumbing suite.
    }

    /**
     * @Given a system named :name with stages :stages starting at :start
     */
    public function createNamedSystem(string $name, string $stages, string $start): void
    {
        $this->authorSystem($name, $stages, $start);
    }

    /**
     * @When the admin authors a :name system with stages :stages starting at :start
     */
    public function authorSystem(string $name, string $stages, string $start): void
    {
        $this->createHandler->handle(new CreateGameSystemCommand(
            name: sprintf('%s-%s', $name, bin2hex(random_bytes(3))),
            description: "$name authored via acceptance test.",
            stageNames: array_map('trim', explode(',', $stages)),
            startingStage: $start,
            transitions: [],
        ));
    }

    /**
     * @Then the player-facing systems list contains :first and :second
     */
    public function listContains(string $first, string $second): void
    {
        $names = array_map(static fn ($summary) => $summary->name, $this->listAvailable->execute());

        foreach ([$first, $second] as $expected) {
            if (!in_array($expected, $names, true) && !str_contains(implode('|', $names), $expected)) {
                throw new AssertionFailedError(sprintf('Expected systems list to contain "%s", got [%s]', $expected, implode(', ', $names)));
            }
        }
    }

    /**
     * @Given the stage :stage of a system named like :prefix is occupied by a campaign
     */
    public function markStageOccupied(string $stage, string $prefix): void
    {
        $system = $this->findByPrefix($prefix);
        \assert($system instanceof GameSystem);

        $this->occupancy->markOccupied($system->id(), $stage);
    }

    /**
     * @When the admin tries to change the flow of a system named like :prefix to stages :stages starting at :start
     */
    public function tryFlowEdit(string $prefix, string $stages, string $start): void
    {
        $system = $this->findByPrefix($prefix);
        \assert($system instanceof GameSystem);

        try {
            $this->updateHandler->handle(new UpdateFlowDefinitionCommand(
                $system->id(),
                array_map('trim', explode(',', $stages)),
                $start,
                [],
            ));
        } catch (\DomainException $e) {
            $this->refusalMessage = $e->getMessage();
        }
    }

    /**
     * @Then the edit is refused because the stage is still occupied
     */
    public function assertRefused(): void
    {
        if ($this->refusalMessage === null || !str_contains($this->refusalMessage, 'occupied')) {
            throw new AssertionFailedError(sprintf('Expected an occupancy refusal, got: %s', var_export($this->refusalMessage, true)));
        }
    }

    private function findByPrefix(string $prefix): ?GameSystem
    {
        foreach ($this->systems->all() as $system) {
            if (str_starts_with($system->name(), $prefix.'-')) {
                return $system;
            }
        }

        return null;
    }
}
