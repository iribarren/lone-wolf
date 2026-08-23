<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\GetCampaignStateHandler;
use App\Campaigns\Application\Query\CampaignState;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Journal\Application\ListJournalEntriesHandler;
use App\Journal\Application\Query\ListJournalEntriesQuery;
use App\Oracles\Application\Command\SaveConsultationToJournal;
use App\Oracles\Application\ConsultOracleHandler;
use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Application\SaveConsultationToJournalHandler;
use App\Oracles\Domain\GlobalScope;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleScope;
use App\Oracles\Domain\SystemScope;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use Behat\Behat\Context\Context;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * US4 acceptance suite (quickstart V4). Oracle authoring runs in-process;
 * the player-facing surface (scoped listing, single-result consultation,
 * save-to-journal) drives the real HTTP kernel through BrowserKit with a
 * minted JWT so contract paths are exercised verbatim.
 */
final class OraclesContext implements Context
{
    private ?User $player = null;

    private ?CampaignState $state = null;

    /** @var array<string, string> scenario oracle name => oracle id */
    private array $oracleIds = [];

    /** @var array<string, string> scenario oracle name => persisted (unique) title */
    private array $oracleTitles = [];

    /** @var array<string, list<string>> scenario system prefix => created system ids */
    private array $systemIds = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $listing = null;

    /** @var array<string, mixed>|null */
    private ?array $outcome = null;

    private ?string $lastConsultedTitle = null;

    public function __construct(
        private readonly KernelBrowser $client,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepositoryInterface $users,
        private readonly CreateGameSystemHandler $createSystem,
        private readonly StartCampaignHandler $startCampaign,
        private readonly OracleRepositoryInterface $oracles,
        private readonly SaveConsultationToJournalHandler $saveToJournal,
        private readonly ListJournalEntriesHandler $listJournal,
    ) {
    }

    /**
     * @Given a game system named like :prefix exists
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
     * @Given I am playing on the system named like :prefix
     */
    public function playingOnSystem(string $prefix): void
    {
        $this->player = User::register(
            \App\Shared\Domain\Identifier\UserId::generate(),
            sprintf('oracle-%s@example.com', bin2hex(random_bytes(5))),
            'hash',
        );
        $this->users->save($this->player);

        $systemId = $this->findLatestSystemId($prefix);

        if ($systemId === null) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

        $this->state = $this->startCampaign->handle(new StartCampaignCommand($this->player->id(), $systemId));
    }

    /**
     * @Given a global oracle :title with entries :entries
     *
     * Entries format: "Text|weight, Text|weight".
     */
    public function createGlobalOracle(string $title, string $entries): void
    {
        $this->createOracle($title, new GlobalScope(), $entries);
    }

    /**
     * @Given an oracle :title scoped to the system named like :prefix with entries :entries
     */
    public function createScopedOracle(string $title, string $prefix, string $entries): void
    {
        $systemId = $this->findLatestSystemId($prefix);

        if ($systemId === null) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

        $this->createOracle($title, new SystemScope($systemId), $entries);
    }

    /**
     * Titles get a unique suffix because integration storage persists across
     * runs and a system-scoped table is unique per system (FR-008 index).
     */
    private function createOracle(string $name, OracleScope $scope, string $entries): void
    {
        $title = sprintf('%s %s', $name, bin2hex(random_bytes(3)));

        $oracle = Oracle::start(\App\Shared\Domain\Identifier\OracleId::generate(), $title, $scope);

        foreach ($this->parseEntries($entries) as [$text, $weight]) {
            $oracle = $oracle->addEntry($text, $weight);
        }

        $this->oracles->save($oracle);
        $this->oracleIds[$name] = $oracle->id()->toString();
        $this->oracleTitles[$name] = $title;
    }

    /**
     * @When I list my campaign's oracles over HTTP
     */
    public function listOraclesOverHttp(): void
    {
        $content = $this->requestJson('GET', sprintf('/api/campaigns/%s/oracles', $this->campaignId()));

        $decoded = json_decode($content, true);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new AssertionFailedError('The oracle listing response is not a JSON array.');
        }

        /** @var list<array<string, mixed>> $decoded */
        $this->listing = $decoded;
    }

    /**
     * @When I consult the oracle :title over HTTP
     */
    public function consultOverHttp(string $title): void
    {
        $oracleId = $this->oracleIds[$title] ?? null;

        if ($oracleId === null) {
            throw new AssertionFailedError(sprintf('No oracle "%s" was authored in this scenario.', $title));
        }

        $this->lastConsultedTitle = $title;

        $body = $this->requestJson(
            'POST',
            sprintf('/api/campaigns/%s/oracles/%s/consult', $this->campaignId(), $oracleId),
            ['save' => false],
        );

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new AssertionFailedError('The consultation response is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        $this->outcome = $decoded;
    }

    /**
     * @Then the listing contains :title
     */
    public function assertListingContains(string $title): void
    {
        if ($this->titlesInListing() === []) {
            throw new AssertionFailedError('No listing was captured.');
        }

        if (!in_array($this->persistedTitle($title), $this->titlesInListing(), true)) {
            throw new AssertionFailedError(sprintf(
                'Expected listing to contain "%s", got [%s].',
                $title,
                implode(', ', $this->titlesInListing()),
            ));
        }
    }

    /**
     * @Then the listing does not contain :title
     */
    public function assertListingExcludes(string $title): void
    {
        if (in_array($this->persistedTitle($title), $this->titlesInListing(), true)) {
            throw new AssertionFailedError(sprintf('Expected listing to exclude "%s".', $title));
        }
    }

    /**
     * @Then the consultation answers status :status
     */
    public function assertOutcomeStatus(string $status): void
    {
        if (($this->outcome['status'] ?? null) !== $status) {
            throw new AssertionFailedError(sprintf(
                'Expected consultation status "%s", got %s.',
                $status,
                var_export($this->outcome['status'] ?? null, true),
            ));
        }
    }

    /**
     * @Then the consultation carries exactly one entry of weight-consistent shape
     */
    public function assertSingleEntry(): void
    {
        $entry = $this->outcome['entry'] ?? null;

        if (!is_array($entry)) {
            throw new AssertionFailedError('The selected outcome carries no entry payload.');
        }

        $text = $entry['text'] ?? null;

        if (!is_string($entry['entryId'] ?? null) || !is_string($text) || trim($text) === '') {
            throw new AssertionFailedError(sprintf('Malformed entry payload: %s.', var_export($entry, true)));
        }
    }

    /**
     * @When I save that consultation to my journal
     */
    public function saveConsultationToJournal(): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        if ($this->outcome === null || ($this->outcome['status'] ?? null) !== 'selected') {
            throw new AssertionFailedError('A selected consultation is required before saving.');
        }

        $entry = is_array($this->outcome['entry'] ?? null) ? $this->outcome['entry'] : [];
        $text = $entry['text'] ?? null;

        if (!is_string($text)) {
            throw new AssertionFailedError('The consultation carries no savable entry text.');
        }

        $title = $this->lastConsultedTitle;

        if ($title === null) {
            throw new AssertionFailedError('No consultation happened before saving.');
        }

        // Replays the shown result into the journal, as the drawer's save
        // action does (US4 scenario 3).
        $this->saveToJournal->handle(new SaveConsultationToJournal(
            $this->player->id(),
            \App\Shared\Domain\Identifier\CampaignId::fromString($this->state->campaignId),
            $this->persistedTitle($title),
            $text,
        ));
    }

    /**
     * @Then my journal records an oracle_result entry containing :text
     */
    public function assertJournalHasOracleResult(string $text): void
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        $entries = $this->listJournal->handle(new ListJournalEntriesQuery(
            $this->player->id(),
            \App\Shared\Domain\Identifier\CampaignId::fromString($this->state->campaignId),
        ))->entries;

        foreach ($entries as $entry) {
            $snapshot = $entry->oracleSnapshot();

            if ($snapshot !== null && str_contains($snapshot->resultText, $text)) {
                return;
            }
        }

        throw new AssertionFailedError(sprintf('No oracle_result entry contains "%s".', $text));
    }

    private function persistedTitle(string $name): string
    {
        return $this->oracleTitles[$name]
            ?? throw new AssertionFailedError(sprintf('No oracle "%s" was authored in this scenario.', $name));
    }

    /** @return list<string> */
    private function titlesInListing(): array
    {
        if (!is_array($this->listing)) {
            return [];
        }

        $titles = [];

        foreach ($this->listing as $row) {
            if (is_array($row) && is_string($row['title'] ?? null)) {
                $titles[] = $row['title'];
            }
        }

        return $titles;
    }

    /**
     * The latest system created under this scenario prefix — older persisted
     * systems from previous runs may already own their one scoped table.
     */
    private function findLatestSystemId(string $prefix): ?\App\Shared\Domain\Identifier\GameSystemId
    {
        $id = $this->systemIds[$prefix][array_key_last($this->systemIds[$prefix] ?? [])] ?? null;

        return $id === null ? null : \App\Shared\Domain\Identifier\GameSystemId::fromString($id);
    }

    private function campaignId(): string
    {
        if (!$this->player instanceof User || !$this->state instanceof CampaignState) {
            throw new AssertionFailedError('A player with an open campaign is required first.');
        }

        return $this->state->campaignId;
    }

    /**
     * Drives the real kernel through BrowserKit with a minted JWT.
     */
    /**
     * @param array<string, mixed>|null $payload
     */
    private function requestJson(string $method, string $path, ?array $payload = null): string
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

        $response = $this->client->getResponse();
        $content = $response->getContent();

        if ($content === false) {
            throw new AssertionFailedError('The response body could not be read.');
        }

        if ($response->getStatusCode() !== 200) {
            throw new AssertionFailedError(sprintf('%s %s answered %d: %s.', $method, $path, $response->getStatusCode(), $content));
        }

        return $content;
    }

    /**
     * @return list<array{string, int}>
     */
    private function parseEntries(string $entries): array
    {
        $parsed = [];

        foreach (explode(',', $entries) as $chunk) {
            $parts = array_map('trim', explode('|', $chunk));
            $text = (string) ($parts[0] ?? '');
            $weight = (int) ($parts[1] ?? 0);
            $parsed[] = [$text, $weight];
        }

        return $parsed;
    }
}
