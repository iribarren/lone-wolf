<?php

declare(strict_types=1);

namespace App\Tests\Integration\Rulesets;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\StartCampaignHandler;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Integration\Support\SignsInAsAdmin;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Backoffice regression coverage for the Rulesets sections:
 * - the systems list renders jsonb-backed rows (TextConfigurator crash),
 * - the dedicated Campaign flows section is reachable from the menu and its
 *   structured editor opens for every system,
 * - the systems NEW page instantiates a valid blank aggregate,
 * - the editor's Save button actually persists (A6: every mapped field needs
 *   a real setter or Symfony's DataMapper dies on the getter).
 */
final class AdminGameFlowPagesTest extends WebTestCase
{
    use SignsInAsAdmin;

    /** EasyAdmin names the CRUD form after the bound entity class. */
    private const FORM = 'PersistenceGameSystem';

    public function testSystemsIndexRendersRowsWithJsonbPayloads(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();

        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Game systems');
        $client->click($crawler->selectLink('Game systems')->link());
        self::assertResponseIsSuccessful();

        // The table paginates across thousands of fixture rows; EA's index
        // search narrows to the freshly created jsonb-backed row.
        $client->request('GET', '/admin/system?query='.urlencode($system['name']));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $system['name']);
    }

    public function testSystemsNewPageRendersWithSeededFlow(): void
    {
        $client = $this->adminClient();

        $client->request('GET', $this->route('admin_dashboard_system_new'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Campaign flow');
        self::assertSelectorExists('select.js-flow-stage-select');
    }

    public function testCampaignFlowsSectionOpensTheStructuredEditor(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();

        // Menu entry (the reported gap) links to the dedicated section.
        $crawler = $client->request('GET', '/admin');
        self::assertSelectorTextContains('body', 'Campaign flows');
        $client->click($crawler->selectLink('Campaign flows')->link());
        self::assertResponseIsSuccessful();

        $client->request(
            'GET',
            $this->route('admin_dashboard_game_flow_edit', ['entityId' => $system['id']]),
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Starting stage');
        // Two structured collections (stages + transitions) with prototypes,
        // plus the starting-stage select populated from stage names client-side.
        self::assertSelectorCount(2, '[data-prototype]');
        self::assertSelectorExists('select[name$="[starting_stage]"]');
    }

    public function testSavingTheFlowEditorUntouchedLeavesTheStoredPayloadIdentical(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();
        $before = $this->storedFlow($system['id']);

        // Re-post the stored payload verbatim: that is what the browser sends
        // when nothing is touched, once the editor has filled the stage
        // selects it renders empty.
        $this->submitFlowEditor($client, $system['id'], $this->decodedFlow($system['id']));

        self::assertSaveSucceeded($client);
        self::assertSame($before, $this->storedFlow($system['id']));
    }

    public function testMovingTheStartingStagePersistsExactlyThatChange(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();
        $before = $this->decodedFlow($system['id']);

        $edited = $before;
        $edited['starting_stage'] = 'Setup';
        $this->submitFlowEditor($client, $system['id'], $edited);

        self::assertSaveSucceeded($client);

        $after = $this->decodedFlow($system['id']);
        self::assertSame('Setup', $after['starting_stage']);
        self::assertSame($before['stages'], $after['stages'], 'Stages must survive a starting-stage edit.');
        self::assertSame($before['transitions'], $after['transitions'], 'Transitions must survive a starting-stage edit.');
    }

    public function testAddingATransitionRowPersistsIt(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();
        $before = $this->decodedFlow($system['id']);

        $edited = $before;
        $edited['transitions'][] = ['from' => 'Setup', 'to' => 'Sequel'];
        $this->submitFlowEditor($client, $system['id'], $edited);

        self::assertSaveSucceeded($client);

        $after = $this->decodedFlow($system['id']);
        self::assertSame(
            [...$before['transitions'], ['from' => 'Setup', 'to' => 'Sequel']],
            $after['transitions'],
        );
        self::assertSame($before['stages'], $after['stages']);
    }

    /**
     * FR-005 through the form: the refusal must reach the author as a flash,
     * not as an exception page, and must leave storage untouched.
     */
    public function testEditOrphaningAnOccupiedStageIsRefusedWithAFlashMessage(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();
        $this->occupyStartingStage($system['id']);
        $before = $this->storedFlow($system['id']);

        // "Scene" is where the campaign sits — renaming it orphans that stage.
        $edited = $this->decodedFlow($system['id']);
        $edited['stages'] = array_map(
            static fn (array $stage): array => $stage['name'] === 'Scene'
                ? ['name' => 'Renamed', 'guidance' => $stage['guidance']]
                : $stage,
            $edited['stages'],
        );
        $edited['starting_stage'] = 'Renamed';
        $edited['transitions'] = [];
        $this->submitFlowEditor($client, $system['id'], $edited);

        self::assertSaveSucceeded($client);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'occupied');
        self::assertSame($before, $this->storedFlow($system['id']));
    }

    /**
     * @param array{stages: list<array{name: string, guidance: string}>, starting_stage: string, transitions: list<array{from: string, to: string}>} $flow
     */
    private function submitFlowEditor(KernelBrowser $client, string $systemId, array $flow): void
    {
        $crawler = $client->request(
            'GET',
            $this->route('admin_dashboard_game_flow_edit', ['entityId' => $systemId]),
        );
        $form = $crawler->selectButton('Save changes')->form();

        // The stage selects carry no server-side option list — the editor
        // builds them client-side and LenientStageNameLoader accepts whatever
        // comes back — so DomCrawler would reject setValue() on them. Post the
        // form's own payload instead, exactly as the browser does.
        $values = $form->getPhpValues();
        $entity = is_array($values[self::FORM] ?? null) ? $values[self::FORM] : [];
        $entity['flowDefinition'] = $flow;
        $values[self::FORM] = $entity;

        $client->request($form->getMethod(), $form->getUri(), $values);
    }

    private static function assertSaveSucceeded(KernelBrowser $client): void
    {
        $status = $client->getResponse()->getStatusCode();

        self::assertLessThan(
            400,
            $status,
            sprintf('Saving the admin form returned HTTP %d instead of persisting.', $status),
        );
    }

    private function storedFlow(string $systemId): string
    {
        return $this->storedColumn('SELECT flow_definition::text FROM game_systems WHERE id = ?', $systemId);
    }

    /**
     * @return array{stages: list<array{name: string, guidance: string}>, starting_stage: string, transitions: list<array{from: string, to: string}>}
     */
    private function decodedFlow(string $systemId): array
    {
        $decoded = json_decode($this->storedFlow($systemId), true);
        self::assertIsArray($decoded);

        /** @var array{stages: list<array{name: string, guidance: string}>, starting_stage: string, transitions: list<array{from: string, to: string}>} $decoded */
        return $decoded;
    }

    private function occupyStartingStage(string $systemId): void
    {
        $container = static::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);
        $startCampaign = $container->get(StartCampaignHandler::class);
        \assert($startCampaign instanceof StartCampaignHandler);

        $player = User::register(
            UserId::generate(),
            sprintf('flow-save-%s@integration.test', bin2hex(random_bytes(4))),
            'hash',
        );
        $users->save($player);

        $startCampaign->handle(new StartCampaignCommand($player->id(), GameSystemId::fromString($systemId)));
    }

    /**
     * The demo-shaped fixture: three stages and a full transition ring, so an
     * edit to one key can be proven not to disturb the rest.
     *
     * @return array{id: string, name: string}
     */
    private function createSystem(): array
    {
        $handler = static::getContainer()->get(CreateGameSystemHandler::class);
        \assert($handler instanceof CreateGameSystemHandler);

        $name = 'Admin pages '.bin2hex(random_bytes(4));
        $id = $handler->handle(new CreateGameSystemCommand(
            name: $name,
            description: 'Backoffice fixture.',
            stageNames: ['Setup', 'Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [
                ['from' => 'Setup', 'to' => 'Scene'],
                ['from' => 'Scene', 'to' => 'Sequel'],
                ['from' => 'Sequel', 'to' => 'Setup'],
            ],
        ));

        return ['id' => $id->toString(), 'name' => $name];
    }
}
