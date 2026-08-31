<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\Query\CampaignState;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Journal\Application\ListJournalEntriesHandler;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use Behat\Behat\Context\Context;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * US6 acceptance suite (quickstart V6). Every player-facing dice step
 * drives the real HTTP kernel through BrowserKit with a minted JWT so the
 * contract paths, the 422 DiceNotationProblem body and the logged-roll
 * payload are exercised verbatim; journal verification reads back through
 * the Journal read model.
 */
final class DiceContext implements Context
{
    private ?User $player = null;

    private ?CampaignState $state = null;

    /** @var array<string, list<string>> scenario system prefix => created system ids */
    private array $systemIds = [];

    private int $responseStatus = 0;

    /** @var array<string, mixed>|null */
    private ?array $responseBody = null;

    private ?int $loggedTotal = null;

    public function __construct(
        private readonly KernelBrowser $client,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepositoryInterface $users,
        private readonly CreateGameSystemHandler $createSystem,
        private readonly StartCampaignHandler $startCampaign,
        private readonly ListJournalEntriesHandler $listJournal,
    ) {
    }

    /**
     * @Given a dice game system named like :prefix exists
     */
    public function createSystem(string $prefix): void
    {
        $systemId = $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('%s-%s', $prefix, bin2hex(random_bytes(3))),
            description: "$prefix authored via acceptance test.",
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [['from' => 'Scene', 'to' => 'Sequel']],
        ));

        $this->systemIds[$prefix][] = $systemId->toString();
    }

    /**
     * @Given I am playing a campaign on the system named like :prefix
     */
    public function playingOnSystem(string $prefix): void
    {
        $this->player = User::register(
            \App\Shared\Domain\Identifier\UserId::generate(),
            sprintf('dice-%s@example.com', bin2hex(random_bytes(5))),
            'hash',
        );
        $this->users->save($this->player);

        $latest = $this->systemIds[$prefix] ?? [];

        if ($latest === []) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

        /** @var string $systemId */
        $systemId = $latest[array_key_last($latest)];

        $this->state = $this->startCampaign->handle(new StartCampaignCommand(
            $this->player->id(),
            \App\Shared\Domain\Identifier\GameSystemId::fromString($systemId),
        ));
    }

    /**
     * @When I roll :notation over HTTP
     */
    public function rollOverHttp(string $notation): void
    {
        $this->requestJson('POST', '/api/dice/roll', ['notation' => $notation]);
    }

    /**
     * @When I log the roll of :notation into my journal over HTTP
     */
    public function logRollOverHttp(string $notation): void
    {
        $this->requestJson(
            'POST',
            sprintf('/api/campaigns/%s/rolls', $this->campaignId()),
            ['notation' => $notation],
        );

        if ($this->responseStatus !== 201) {
            throw new AssertionFailedError(sprintf(
                'Logging a roll answered %d instead of 201: %s.',
                $this->responseStatus,
                var_export($this->responseBody, true),
            ));
        }

        $roll = is_array($this->responseBody['roll'] ?? null) ? $this->responseBody['roll'] : [];
        $total = $roll['total'] ?? null;
        $this->loggedTotal = is_int($total) ? $total : null;
    }

    /**
     * @Then the roll shows exactly :count die within :min and :max
     * @Then the roll shows exactly :count dice within :min and :max
     */
    public function assertDiceShown(int $count, int $min, int $max): void
    {
        $diceValues = $this->resultField('diceValues');

        if (!is_array($diceValues)) {
            throw new AssertionFailedError('The roll result carries no diceValues list.');
        }

        if (count($diceValues) !== $count) {
            throw new AssertionFailedError(sprintf(
                'Expected %d shown dice, got %s.',
                $count,
                var_export($diceValues, true),
            ));
        }

        foreach ($diceValues as $value) {
            if (!is_int($value) || $value < $min || $value > $max) {
                throw new AssertionFailedError(sprintf(
                    'A shown die %s falls outside [%d,%d].',
                    var_export($value, true),
                    $min,
                    $max,
                ));
            }
        }
    }

    /**
     * @Then the roll total lies between :min and :max
     */
    public function assertTotalBounded(int $min, int $max): void
    {
        $total = $this->resultField('total');

        if (!is_int($total) || $total < $min || $total > $max) {
            throw new AssertionFailedError(sprintf(
                'Expected total in [%d,%d], got %s.',
                $min,
                $max,
                var_export($total, true),
            ));
        }
    }

    /**
     * @Then the roll total equals the shown dice summed with modifier :modifier
     */
    public function assertTotalIsTheDiceSum(int $modifier): void
    {
        $diceValues = $this->resultField('diceValues');
        $total = $this->resultField('total');

        if (!is_array($diceValues) || !is_int($total)) {
            throw new AssertionFailedError('The result payload is incomplete.');
        }

        $expected = array_sum($diceValues) + $modifier;

        // FR-028 verbatim: total = Σ diceValues ± modifier.
        if ($total !== $expected) {
            throw new AssertionFailedError(sprintf(
                'Expected total %d (Σ dice ± modifier), got %d.',
                $expected,
                $total,
            ));
        }
    }

    /**
     * @Then the roll is refused with reason :reason
     */
    public function assertRefusedWithReason(string $reason): void
    {
        if ($this->responseStatus !== 422) {
            throw new AssertionFailedError(sprintf(
                'Expected a 422 refusal, got %d with body %s.',
                $this->responseStatus,
                var_export($this->responseBody, true),
            ));
        }

        $actual = $this->responseBody['reason'] ?? null;

        if ($actual !== $reason) {
            throw new AssertionFailedError(sprintf(
                'Expected refusal reason "%s", got "%s".',
                $reason,
                var_export($actual, true),
            ));
        }
    }

    /**
     * @Then no result is shown
     */
    public function assertNoResultShown(): void
    {
        foreach (['diceValues', 'modifier', 'total'] as $forbidden) {
            if (array_key_exists($forbidden, $this->responseBody ?? [])) {
                throw new AssertionFailedError(sprintf(
                    'A refused roll must not carry "%s" — no result may exist.',
                    $forbidden,
                ));
            }
        }
    }

    /**
     * @Then my journal records a dice_roll entry for :notation
     */
    public function assertJournalHasDiceRoll(string $notation): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        $entries = $this->listJournal->handle(new ListJournalEntriesQuery(
            $this->player->id(),
            \App\Shared\Domain\Identifier\CampaignId::fromString($this->state->campaignId),
        ))->entries;

        foreach ($entries as $entry) {
            $snapshot = $entry->rollSnapshot();

            if ($snapshot === null || $snapshot->notation !== $notation) {
                continue;
            }

            if ($this->loggedTotal !== null && $snapshot->total !== $this->loggedTotal) {
                throw new AssertionFailedError(sprintf(
                    'Journal snapshot total %d differs from the shown roll total %d.',
                    $snapshot->total,
                    $this->loggedTotal,
                ));
            }

            // The journal records WHEN it happened — a real timestamp must
            // travel with the snapshot (quickstart V6 row 4).
            if ($entry->createdAt()->getTimestamp() <= 0) {
                throw new AssertionFailedError('The dice_roll entry lacks a usable timestamp.');
            }

            return;
        }

        throw new AssertionFailedError(sprintf('No dice_roll entry records "%s".', $notation));
    }

    /**
     * The contract answers the logged roll with the result and the created
     * entry embedded, never as IRI references — the player app renders both
     * straight from this body (openapi.yaml /campaigns/{campaignId}/rolls).
     *
     * @Then the logged roll answers with the roll and the journal entry embedded
     */
    public function assertLoggedRollEmbedsBothPayloads(): void
    {
        $roll = $this->responseBody['roll'] ?? null;

        if (!is_array($roll)) {
            throw new AssertionFailedError(sprintf(
                'The logged roll must carry an embedded roll object, got %s.',
                var_export($roll, true),
            ));
        }

        foreach (['notation', 'diceValues', 'modifier', 'total'] as $field) {
            if (!array_key_exists($field, $roll)) {
                throw new AssertionFailedError(sprintf(
                    'The embedded roll lacks "%s": %s.',
                    $field,
                    var_export($roll, true),
                ));
            }
        }

        $entry = $this->responseBody['journalEntry'] ?? null;

        if (!is_array($entry)) {
            throw new AssertionFailedError(sprintf(
                'The logged roll must carry an embedded journal entry object, got %s.',
                var_export($entry, true),
            ));
        }

        foreach (['id', 'stageName', 'kind', 'createdAt'] as $field) {
            if (!array_key_exists($field, $entry)) {
                throw new AssertionFailedError(sprintf(
                    'The embedded journal entry lacks "%s": %s.',
                    $field,
                    var_export($entry, true),
                ));
            }
        }

        if ($entry['kind'] !== 'dice_roll') {
            throw new AssertionFailedError(sprintf(
                'The embedded journal entry records kind "%s" instead of "dice_roll".',
                var_export($entry['kind'], true),
            ));
        }
    }

    private function resultField(string $field): mixed
    {
        if ($this->responseStatus !== 200) {
            throw new AssertionFailedError(sprintf(
                'A valid roll was expected to answer 200, got %d with body %s.',
                $this->responseStatus,
                var_export($this->responseBody, true),
            ));
        }

        return $this->responseBody[$field] ?? null;
    }

    private function campaignId(): string
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        return $this->state->campaignId;
    }

    /**
     * Drives the real kernel through BrowserKit with a minted JWT, capturing
     * status and decoded JSON without failing on non-2xx — refusals are part
     * of what this suite asserts.
     *
     * @param array<string, mixed>|null $payload
     */
    private function requestJson(string $method, string $path, ?array $payload = null): void
    {
        if (!$this->player instanceof User) {
            throw new AssertionFailedError('A registered player is required first.');
        }

        $token = $this->jwtManager->create(new SecurityUser($this->player));

        $this->client->request(
            $method,
            $path,
            [],
            [],
            [
                'HTTP_Authorization' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload === null ? null : (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $content = $this->client->getResponse()->getContent();
        $decoded = is_string($content) ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : null;

        $this->responseStatus = $this->client->getResponse()->getStatusCode();

        if (is_array($decoded)) {
            /** @var array<string, mixed> $decoded */
            $this->responseBody = $decoded;
        } else {
            $this->responseBody = null;
        }
    }
}
