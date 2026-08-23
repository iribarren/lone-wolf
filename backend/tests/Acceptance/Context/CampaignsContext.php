<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Campaigns\Application\AdvanceStageHandler;
use App\Campaigns\Application\AppendNarrativeEntryHandler;
use App\Campaigns\Application\Command\AdvanceStageCommand;
use App\Campaigns\Application\Command\AppendNarrativeEntryCommand;
use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\GetCampaignStateHandler;
use App\Campaigns\Application\StartCampaignHandler;
use App\Campaigns\Application\Query\CampaignState;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Journal\Application\ListJournalEntriesHandler;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Shared\Domain\Identifier\CampaignId;
use Behat\Behat\Context\Context;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Guided-play acceptance suite (US2 quickstart V3). Play-loop steps run
 * in-process like RulesetsContext; the illegal-move refusal drives the real
 * HTTP kernel through BrowserKit with a minted JWT so the 422 problem+json
 * body (legalAlternatives) is asserted verbatim (FR-016).
 */
final class CampaignsContext implements Context
{
    private ?User $player = null;

    private ?CampaignState $state = null;

    private ?int $responseStatus = null;

    private mixed $responseBody = null;

    public function __construct(
        private readonly KernelBrowser $client,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepositoryInterface $users,
        private readonly RulesetRepositoryInterface $systems,
    private readonly CreateGameSystemHandler $createSystem,
        private readonly StartCampaignHandler $startCampaign,
        private readonly AdvanceStageHandler $advanceStage,
        private readonly GetCampaignStateHandler $getState,
        private readonly AppendNarrativeEntryHandler $appendNarrative,
        private readonly ListJournalEntriesHandler $listJournal,
    ) {
    }

    /**
     * @Given a system named like :prefix exists with stages :stages starting at :start where :from leads to :to
     */
    public function createSystemWithTransition(string $prefix, string $stages, string $start, string $from, string $to): void
    {
        $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('%s-%s', $prefix, bin2hex(random_bytes(3))),
            description: "$prefix authored via acceptance test.",
            stageNames: array_map('trim', explode(',', $stages)),
            startingStage: $start,
            transitions: [['from' => $from, 'to' => $to]],
        ));
    }

    /**
     * @Given I am a registered player
     */
    public function registeredPlayer(): void
    {
        $this->player = User::register(
            \App\Shared\Domain\Identifier\UserId::generate(),
            sprintf('guided-%s@example.com', bin2hex(random_bytes(5))),
            'hash',
        );
        $this->users->save($this->player);
    }

    /**
     * @When I start a campaign on the system named like :prefix
     */
    public function startCampaign(string $prefix): void
    {
        $this->startCampaignOn($prefix);
    }

    /**
     * @Given I started a campaign on the system named like :prefix
     */
    public function startedCampaign(string $prefix): void
    {
        $this->startCampaignOn($prefix);
    }

    /**
     * @Then my campaign opens at stage :stage
     */
    public function assertOpensAt(string $stage): void
    {
        $this->assertCurrentStage($stage);
    }

    /**
     * @When I try to advance over HTTP to stage :stage
     */
    public function tryAdvanceOverHttp(string $stage): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        $token = $this->jwtManager->create(new SecurityUser($this->player));

        $this->client->request(
            'POST',
            sprintf('/api/campaigns/%s/advance', $this->state->campaignId),
            [],
            [],
            [
                'HTTP_Authorization' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            (string) json_encode(['toStageId' => $stage], JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        $content = $response->getContent();

        if ($content === false) {
            throw new AssertionFailedError('The response body could not be read.');
        }

        $this->responseStatus = $response->getStatusCode();
        $this->responseBody = json_decode($content, true);
    }

    /**
     * @Then the refusal carries status :expected
     */
    public function assertResponseStatus(int $expected): void
    {
        if ($this->responseStatus !== $expected) {
            throw new AssertionFailedError(
                sprintf('Expected status %d but got %s.', $expected, var_export($this->responseStatus, true)),
            );
        }
    }

    /**
     * @Then the refusal names the legal alternatives :alternatives
     */
    public function assertRefusalAlternatives(string $alternatives): void
    {
        if (!is_array($this->responseBody)) {
            throw new AssertionFailedError('No refusal body was captured.');
        }

        $rawAlternatives = $this->responseBody['legalAlternatives'] ?? null;

        if (!is_array($rawAlternatives)) {
            throw new AssertionFailedError('The refusal body carries no legalAlternatives list.');
        }

        $named = [];

        foreach ($rawAlternatives as $action) {
            if (!is_array($action)) {
                continue;
            }

            $toStageId = $action['toStageId'] ?? null;
            $named[] = is_string($toStageId) ? $toStageId : '';
        }

        foreach (array_map('trim', explode(',', $alternatives)) as $expected) {
            if (!in_array($expected, $named, true)) {
                throw new AssertionFailedError(
                    sprintf('Expected refusal alternatives to include "%s", got [%s].', $expected, implode(', ', $named)),
                );
            }
        }
    }

    /**
     * @When I advance to stage :stage
     */
    public function advanceTo(string $stage): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        $this->state = $this->advanceStage->handle(new AdvanceStageCommand(
            $this->player->id(),
            CampaignId::fromString($this->state->campaignId),
            $stage,
        ));
    }

    /**
     * @Then my campaign sits at stage :stage
     * @Then my campaign resumes at stage :stage
     */
    public function assertSitsAt(string $stage): void
    {
        $this->assertCurrentStage($stage);
    }

    /**
     * @When I append the narrative :narrative
     */
    public function appendNarrative(string $narrative): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        $this->appendNarrative->handle(new AppendNarrativeEntryCommand(
            $this->player->id(),
            CampaignId::fromString($this->state->campaignId),
            $narrative,
        ));
    }

    /**
     * @Then my journal records :expectedCount entry stamped at stage :stage containing :narrative
     */
    public function assertSingleJournalEntry(int $expectedCount, string $stage, string $narrative): void
    {
        $entries = $this->journalEntries();

        if (count($entries) !== $expectedCount) {
            throw new AssertionFailedError(sprintf('Expected %d journal entries, got %d.', $expectedCount, count($entries)));
        }

        $entry = $entries[0];

        if ($entry->stageName() !== $stage || !str_contains((string) $entry->narrative(), $narrative)) {
            throw new AssertionFailedError(sprintf(
                'Journal mismatch: expected [%s | %s], got [%s | %s].',
                $stage,
                $narrative,
                $entry->stageName(),
                var_export($entry->narrative(), true),
            ));
        }
    }

    /**
     * @Then my journal records 2 entries: :firstCount at stage :firstStage containing :firstText and :secondCount at stage :secondStage containing :secondText
     */
    public function assertTwoJournalEntries(
        int $firstCount,
        string $firstStage,
        string $firstText,
        int $secondCount,
        string $secondStage,
        string $secondText,
    ): void {
        $entries = $this->journalEntries();

        if (count($entries) !== $firstCount + $secondCount) {
            throw new AssertionFailedError(sprintf('Expected %d journal entries, got %d.', $firstCount + $secondCount, count($entries)));
        }

        $expectations = [$firstText => $firstStage, $secondText => $secondStage];
        $seen = [];

        foreach ($entries as $entry) {
            foreach ($expectations as $text => $stageName) {
                if (str_contains((string) $entry->narrative(), $text)) {
                    if ($entry->stageName() !== $stageName) {
                        throw new AssertionFailedError(sprintf(
                            'Entry "%s" should be stamped [%s] but sits on [%s].',
                            $text,
                            $stageName,
                            $entry->stageName(),
                        ));
                    }

                    $seen[$text] = ($seen[$text] ?? 0) + 1;
                }
            }
        }

        if (count($seen) !== count($expectations)) {
            throw new AssertionFailedError('Not every expected journal narrative was found.');
        }
    }

    /**
     * @When I re-open my campaign by id
     */
    public function reopenCampaign(): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        // Fresh fetch through persistence — the exact-resume read (FR-018).
        $this->state = $this->getState->state(
            CampaignId::fromString($this->state->campaignId),
            $this->player->id(),
        );
    }

    private function startCampaignOn(string $prefix): void
    {
        if (!$this->player instanceof User) {
            throw new AssertionFailedError('A registered player is required first.');
        }

        $systemId = null;

        foreach ($this->systems->all() as $system) {
            if (str_starts_with($system->name(), $prefix.'-')) {
                $systemId = $system->id();

                break;
            }
        }

        if ($systemId === null) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

        $this->state = $this->startCampaign->handle(new StartCampaignCommand($this->player->id(), $systemId));
    }

    private function assertCurrentStage(string $stage): void
    {
        if (!$this->state instanceof CampaignState) {
            throw new AssertionFailedError('No campaign state has been fetched yet.');
        }

        if ($this->state->currentStage->stageName !== $stage) {
            throw new AssertionFailedError(sprintf(
                'Expected campaign to sit on stage "%s" but it sits on "%s".',
                $stage,
                $this->state->currentStage->stageName,
            ));
        }
    }

    /**
     * @return list<\App\Journal\Domain\JournalEntry>
     */
    private function journalEntries(): array
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        return $this->listJournal->handle(new ListJournalEntriesQuery(
            $this->player->id(),
            CampaignId::fromString($this->state->campaignId),
        ))->entries;
    }
}
