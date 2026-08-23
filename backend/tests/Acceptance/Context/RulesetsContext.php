<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Campaigns\Application\AdvanceStageHandler;
use App\Campaigns\Application\Command\AdvanceStageCommand;
use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\Query\SuggestedActionView;
use App\Campaigns\Application\StartCampaignHandler;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Shared\Domain\Identifier\UserId;
use Behat\Behat\Context\Context;
use PHPUnit\Framework\AssertionFailedError;

final class RulesetsContext implements Context
{
    private ?string $refusalMessage = null;

    public function __construct(
        private readonly \App\Rulesets\Application\CreateGameSystemHandler $createHandler,
        private readonly \App\Rulesets\Application\UpdateFlowDefinitionHandler $updateHandler,
        private readonly \App\Rulesets\Application\Port\RulesetRepositoryInterface $systems,
        private readonly \App\Rulesets\Application\Query\ListAvailableSystemsQuery $listAvailable,
        private readonly AdvanceStageHandler $advanceStage,
        private readonly StartCampaignHandler $startCampaign,
        private readonly UserRepositoryInterface $users,
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
        $names = array_map(static fn ($summary): string => $summary->name, $this->listAvailable->execute());

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

        if (!$system instanceof \App\Rulesets\Domain\GameSystem) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

        // Occupancy ground truth (FR-005): a real player campaign parked on
        // the named stage.
        $player = User::register(UserId::generate(), sprintf('%s@example.com', bin2hex(random_bytes(5))), 'hash');
        $this->users->save($player);

        $state = $this->startCampaign->handle(new StartCampaignCommand($player->id(), $system->id()));

        $attempts = 0;
        while ($state->currentStage->stageName !== $stage && $attempts < 10) {
            $next = null;
            foreach ($state->currentStage->suggestedActions as $action) {
                if ($action instanceof SuggestedActionView && $action->kind === 'advance') {
                    $next = $action->toStageName;

                    break;
                }
            }

            if ($next === null) {
                break;
            }

            $state = $this->advanceStage->handle(new AdvanceStageCommand($player->id(), \App\Shared\Domain\Identifier\CampaignId::fromString($state->campaignId), $next));
            ++$attempts;
        }

        if ($state->currentStage->stageName !== $stage) {
            throw new AssertionFailedError(sprintf('Could not park a campaign on stage "%s".', $stage));
        }
    }

    /**
     * @When the admin tries to change the flow of a system named like :prefix to stages :stages starting at :start
     */
    public function tryFlowEdit(string $prefix, string $stages, string $start): void
    {
        $system = $this->findByPrefix($prefix);

        if (!$system instanceof \App\Rulesets\Domain\GameSystem) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

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

    private function findByPrefix(string $prefix): ?\App\Rulesets\Domain\GameSystem
    {
        foreach ($this->systems->all() as $system) {
            if (str_starts_with($system->name(), $prefix.'-')) {
                return $system;
            }
        }

        return null;
    }
}
