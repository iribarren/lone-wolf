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
use App\Tests\Integration\Support\SupersedeSimulator;
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

    /**
     * C3: EasyAdmin titles a page from the bound entity class unless the CRUD
     * sets an entity label, so the backoffice read "Edit PersistenceGameSystem".
     */
    public function testRulesetsPagesAreTitledInTheDomainLanguage(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();

        foreach ([
            $this->route('admin_dashboard_system_new') => 'Game system',
            $this->route('admin_dashboard_system_edit', ['entityId' => $system['id']]) => 'Game system',
            $this->route('admin_dashboard_game_flow_edit', ['entityId' => $system['id']]) => 'Campaign flow',
        ] as $url => $expected) {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful();

            $heading = $crawler->filter('h1')->text();
            self::assertStringNotContainsString('Persistence', $heading, $url);
            self::assertStringContainsString($expected, $heading, $url);
        }
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
            [...array_map(self::arrow(...), $before['transitions']), 'Setup -> Sequel'],
            array_map(self::arrow(...), $after['transitions']),
        );
        self::assertSame($before['stages'], $after['stages']);
    }

    /**
     * The Systems edit form maps plain scalars plus the sheet-structure jsonb
     * document — the third form the A6 report caught returning 500.
     */
    public function testSavingASystemsDescriptionAndSheetStructurePersistsThem(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();

        $crawler = $client->request(
            'GET',
            $this->route('admin_dashboard_system_edit', ['entityId' => $system['id']]),
        );
        self::assertResponseIsSuccessful();

        $sheet = [
            'version' => 1,
            'fields' => [[
                'key' => 'grit',
                'label' => 'Grit',
                'type' => 'number',
                'required_for_pc' => true,
                'required_for_npc' => false,
                'options' => [],
            ]],
        ];

        $form = $crawler->selectButton('Save changes')->form();
        $form[self::FORM.'[description]'] = 'Edited in the backoffice.';
        $form[self::FORM.'[sheetStructure]'] = (string) json_encode($sheet, JSON_THROW_ON_ERROR);
        $client->submit($form);

        self::assertSaveSucceeded($client);

        self::assertSame(
            'Edited in the backoffice.',
            $this->storedColumn('SELECT description FROM game_systems WHERE id = ?', $system['id']),
        );

        $stored = json_decode(
            $this->storedColumn('SELECT sheet_structure::text FROM game_systems WHERE id = ?', $system['id']),
            true,
        );
        self::assertIsArray($stored);
        $fields = $stored['fields'] ?? null;
        self::assertIsArray($fields);
        self::assertCount(1, $fields);
        $field = $fields[0] ?? null;
        self::assertIsArray($field);
        self::assertSame('grit', $field['key'] ?? null);
    }

    /**
     * Guidance is stage prose the editor authors alongside the structure
     * (FR-013/FR-014). The update command carries names only, so it has to be
     * carried across explicitly — and a second, untouched save must then be a
     * true no-op down to the stored bytes.
     */
    public function testAuthoredStageGuidanceSurvivesTheSaveAndTheNextOne(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();

        $authored = $this->decodedFlow($system['id']);
        $authored['stages'] = array_map(
            static fn (array $stage): array => [
                'name' => $stage['name'],
                'guidance' => 'How to play '.$stage['name'].'.',
            ],
            $authored['stages'],
        );
        $this->submitFlowEditor($client, $system['id'], $authored);
        self::assertSaveSucceeded($client);

        $stored = $this->decodedFlow($system['id']);
        self::assertSame(
            ['How to play Setup.', 'How to play Scene.', 'How to play Sequel.'],
            array_map(static fn (array $stage): string => $stage['guidance'], $stored['stages']),
        );

        $before = $this->storedFlow($system['id']);
        $this->submitFlowEditor($client, $system['id'], $stored);

        self::assertSaveSucceeded($client);
        self::assertSame($before, $this->storedFlow($system['id']));
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
     * FR-002: a structurally invalid edit — here, deleting stage rows until
     * one is left — is a refusal the author has to read, not an exception
     * page. Same contract as the create form, which already flashes it.
     */
    public function testAStructurallyInvalidFlowEditIsRefusedWithAFlashMessage(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();
        $before = $this->storedFlow($system['id']);

        $edited = $this->decodedFlow($system['id']);
        $edited['stages'] = [$edited['stages'][0]];
        $edited['starting_stage'] = 'Setup';
        $edited['transitions'] = [];
        $this->submitFlowEditor($client, $system['id'], $edited);

        self::assertSaveSucceeded($client);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'at least two stages');
        self::assertSame($before, $this->storedFlow($system['id']));
    }

    /**
     * Edge case §8: another admin's save lands between this request loading
     * the row and flushing it. The author must get the supersede warning, not
     * an exception page.
     */
    public function testASupersededFlowEditWarnsInsteadOfCrashing(): void
    {
        $client = $this->adminClient();
        $system = $this->createSystem();
        $before = $this->storedFlow($system['id']);

        $edited = $this->decodedFlow($system['id']);
        $edited['starting_stage'] = 'Setup';

        SupersedeSimulator::$armed = true;

        try {
            $this->submitFlowEditor($client, $system['id'], $edited);
        } finally {
            SupersedeSimulator::$armed = false;
        }

        self::assertSaveSucceeded($client);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'superseded');
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

    /**
     * @param array{from: string, to: string} $transition
     */
    private static function arrow(array $transition): string
    {
        return $transition['from'].' -> '.$transition['to'];
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
