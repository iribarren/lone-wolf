<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Command\UpdateSheetStructureCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\UpdateSheetStructureHandler;
use App\Rulesets\Domain\FieldDefinition;
use Behat\Behat\Context\Context;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * US5 acceptance suite (US5 scenarios 1–3). Systems and their sheet shapes
 * are authored through the Rulesets handlers; character writes drive the
 * real HTTP kernel with a minted JWT so field-level 422 refusals are
 * asserted verbatim against the contract's SheetValidationProblem.
 */
final class CharactersContext implements Context
{
    private ?User $player = null;

    private ?string $campaignId = null;

    private ?string $characterId = null;

    private ?string $characterKind = null;

    /** @var array<string, list<string>> scenario system prefix => created system ids */
    private array $systemIds = [];

    private ?int $responseStatus = null;

    /** @var array<string, mixed>|null */
    private ?array $responseBody = null;

    public function __construct(
        private readonly KernelBrowser $client,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepositoryInterface $users,
        private readonly CreateGameSystemHandler $createSystem,
        private readonly UpdateSheetStructureHandler $updateStructure,
        private readonly StartCampaignHandler $startCampaign,
    ) {
    }

    /**
     * @Given a game system named like :prefix exists with sheet :spec
     *
     * Spec format: comma-separated `key:type:flags[:Option|Option]` where
     * flags may contain `pc` and/or `npc` marking the requirement sets.
     */
    public function createSystemWithSheet(string $prefix, string $spec): void
    {
        $systemId = $this->createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('%s-%s', $prefix, bin2hex(random_bytes(3))),
            description: "$prefix authored via acceptance test.",
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [['from' => 'Scene', 'to' => 'Sequel']],
        ));

        $this->systemIds[$prefix][] = $systemId->toString();
        $this->applySheet($systemId, $spec);
    }

    /**
     * @Given the system named like :prefix gains sheet :spec
     *
     * Bumps/changes an existing system's structure — used for drift setups.
     */
    public function changeSystemSheet(string $prefix, string $spec): void
    {
        $this->applySheet($this->latestSystemId($prefix), $spec);
    }

    /**
     * @Given I am running a campaign on the system named like :prefix
     */
    public function runningCampaignOnSystem(string $prefix): void
    {
        $this->player = User::register(
            \App\Shared\Domain\Identifier\UserId::generate(),
            sprintf('sheets-%s@example.com', bin2hex(random_bytes(5))),
            'hash',
        );
        $this->users->save($this->player);

        $started = $this->startCampaign->handle(new StartCampaignCommand(
            $this->player->id(),
            $this->latestSystemId($prefix),
        ));

        $this->campaignId = $started->campaignId;
    }

    /**
     * @Given I created a\/an :kind named :name with attributes :attributes over HTTP
     * @When I create a\/an :kind named :name with attributes :attributes over HTTP
     */
    public function createCharacterOverHttp(string $kind, string $name, string $attributes): void
    {
        if ($this->campaignId === null) {
            throw new AssertionFailedError('A running campaign is required first.');
        }

        $this->requestJson(
            'POST',
            sprintf('/api/campaigns/%s/characters', $this->campaignId),
            ['kind' => $kind, 'name' => $name, 'attributes' => $this->decodeJson($attributes)],
        );

        $created = $this->responseBody['id'] ?? null;

        if (is_string($created)) {
            $this->characterId = $created;
            $this->characterKind = $kind;
        }
    }

    /**
     * @When I re-save that character as :name with attributes :attributes over HTTP
     */
    public function reSaveCharacterOverHttp(string $name, string $attributes): void
    {
        $this->reSaveCharacterAsKindOverHttp($this->characterKind ?? 'pc', $name, $attributes);
    }

    /**
     * @When I re-save that character as a\/an :kind named :name with attributes :attributes over HTTP
     */
    public function reSaveCharacterAsKindOverHttp(string $kind, string $name, string $attributes): void
    {
        if ($this->characterId === null) {
            throw new AssertionFailedError('A created character is required first.');
        }

        $this->requestJson(
            'PATCH',
            sprintf('/api/characters/%s', $this->characterId),
            ['kind' => $kind, 'name' => $name, 'attributes' => $this->decodeJson($attributes)],
        );
    }

    /**
     * @Then the character is accepted
     */
    public function assertAccepted(): void
    {
        if ($this->responseStatus !== 201) {
            throw new AssertionFailedError(sprintf(
                'Expected the character to be accepted, got %s: %s.',
                var_export($this->responseStatus, true),
                var_export($this->responseBody, true),
            ));
        }
    }

    /**
     * @Then the character is updated
     */
    public function assertUpdated(): void
    {
        if ($this->responseStatus !== 200) {
            throw new AssertionFailedError(sprintf(
                'Expected the character to be updated, got %s: %s.',
                var_export($this->responseStatus, true),
                var_export($this->responseBody, true),
            ));
        }
    }

    /**
     * The contract types `attributes` as an object (openapi.yaml CharacterWrite),
     * and a sheet that requires nothing of an NPC lets a character through with
     * an empty map. PHP encodes an empty array as `[]`, so this has to read the
     * RAW body: json_decode(..., true) renders `{}` and `[]` identically and
     * would pass against the defect.
     *
     * @Then the character answers its attributes as a JSON object
     */
    public function assertAttributesSerialiseAsObject(): void
    {
        $raw = $this->client->getResponse()->getContent();

        if ($raw === false) {
            throw new AssertionFailedError('The response body could not be read.');
        }

        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);

        if (!$decoded instanceof \stdClass || !property_exists($decoded, 'attributes')) {
            throw new AssertionFailedError(sprintf(
                'Expected a character object carrying attributes, got %s.',
                $raw,
            ));
        }

        if (!$decoded->attributes instanceof \stdClass) {
            throw new AssertionFailedError(sprintf(
                'The contract types attributes as an object; the API answered %s in %s.',
                get_debug_type($decoded->attributes),
                $raw,
            ));
        }
    }

    /**
     * @Then that character is named :name
     */
    public function assertStoredName(string $name): void
    {
        $stored = $this->fetchCharacter();

        if (($stored['name'] ?? null) !== $name) {
            throw new AssertionFailedError(sprintf(
                'Expected the stored character to be named "%s", got %s.',
                $name,
                var_export($stored['name'] ?? null, true),
            ));
        }
    }

    /**
     * @Then that character is flagged for review
     */
    public function assertFlagged(): void
    {
        $this->assertReviewStatus('flagged_for_review');
    }

    /**
     * @Then that character is clean
     */
    public function assertClean(): void
    {
        $this->assertReviewStatus('clean');
    }

    /**
     * @Then the sheet refusal carries status :expected
     */
    public function assertRefusalStatus(int $expected): void
    {
        if ($this->responseStatus !== $expected) {
            throw new AssertionFailedError(sprintf(
                'Expected refusal %d but got %s: %s.',
                $expected,
                var_export($this->responseStatus, true),
                var_export($this->responseBody, true),
            ));
        }
    }

    /**
     * @Then the refusal names violations for fields :fields
     */
    public function assertRefusalFields(string $fields): void
    {
        $violations = $this->responseBody['violations'] ?? null;

        if (!is_array($violations)) {
            throw new AssertionFailedError(sprintf('No violations list was captured: %s.', var_export($this->responseBody, true)));
        }

        $named = [];

        foreach ($violations as $violation) {
            if (is_array($violation) && is_string($violation['field'] ?? null)) {
                $named[] = $violation['field'];
            }
        }

        foreach (array_map('trim', explode(',', $fields)) as $expected) {
            if (!in_array($expected, $named, true)) {
                throw new AssertionFailedError(sprintf(
                    'Expected violations for "%s", got [%s].',
                    $expected,
                    implode(', ', $named),
                ));
            }
        }
    }

    private function assertReviewStatus(string $expected): void
    {
        $stored = $this->fetchCharacter();

        if (($stored['reviewStatus'] ?? null) !== $expected) {
            throw new AssertionFailedError(sprintf(
                'Expected review status "%s", got %s (drift issues: %s).',
                $expected,
                var_export($stored['reviewStatus'] ?? null, true),
                var_export($stored['driftIssues'] ?? null, true),
            ));
        }
    }

    /**
     * Reads the character back through the player-facing list projection.
     *
     * @return array<array-key, mixed>
     */
    private function fetchCharacter(): array
    {
        if ($this->campaignId === null || $this->characterId === null) {
            throw new AssertionFailedError('A created character is required first.');
        }

        [$status, $body] = $this->sendJson('GET', sprintf('/api/campaigns/%s/characters', $this->campaignId), null);

        if ($status !== 200 || $body === null) {
            throw new AssertionFailedError(sprintf('Could not list characters (status %d).', $status));
        }

        foreach ($body as $view) {
            if (is_array($view) && ($view['id'] ?? null) === $this->characterId) {
                return $view;
            }
        }

        throw new AssertionFailedError(sprintf('Character %s is not in the campaign listing.', $this->characterId));
    }

    private function applySheet(\App\Shared\Domain\Identifier\GameSystemId $systemId, string $spec): void
    {
        $fields = [];

        foreach (array_map('trim', explode(',', $spec)) as $chunk) {
            $parts = array_map('trim', explode(':', $chunk));
            $key = (string) ($parts[0] ?? '');
            $type = (string) ($parts[1] ?? 'text');
            $flags = (string) ($parts[2] ?? '');
            $options = isset($parts[3]) ? array_values(array_filter(explode('|', $parts[3]), static fn ($o) => $o !== '')) : [];
            $pc = str_contains($flags, 'pc');
            $npc = str_contains($flags, 'npc');

            $fields[] = match ($type) {
                'number' => FieldDefinition::number($key, ucfirst($key), $pc, $npc),
                'select' => FieldDefinition::select($key, ucfirst($key), $options === [] ? ['unspecified'] : $options, $pc, $npc),
                default => FieldDefinition::text($key, ucfirst($key), $pc, $npc),
            };
        }

        $this->updateStructure->handle(new UpdateSheetStructureCommand($systemId, $fields));
    }

    private function latestSystemId(string $prefix): \App\Shared\Domain\Identifier\GameSystemId
    {
        $id = $this->systemIds[$prefix][array_key_last($this->systemIds[$prefix] ?? [])] ?? null;

        if ($id === null) {
            throw new AssertionFailedError(sprintf('No system named like "%s" exists.', $prefix));
        }

        return \App\Shared\Domain\Identifier\GameSystemId::fromString($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            throw new AssertionFailedError(sprintf('Step payload is not JSON: %s.', $payload));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestJson(string $method, string $path, array $payload): void
    {
        [$status, $body] = $this->sendJson($method, $path, $payload);

        $this->responseStatus = $status;

        if ($body !== null) {
            /** @var array<string, mixed> $body */
            $this->responseBody = $body;
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{0: int, 1: array<array-key, mixed>|null}
     */
    private function sendJson(string $method, string $path, ?array $payload): array
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

        $decoded = json_decode($content, true);

        return [$response->getStatusCode(), is_array($decoded) ? $decoded : null];
    }
}
