<?php

declare(strict_types=1);

namespace App\Tests\Integration\Rulesets;

use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\HashingSubject;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Backoffice regression coverage for the Rulesets sections:
 * - the systems list renders jsonb-backed rows (TextConfigurator crash),
 * - the dedicated Campaign flows section is reachable from the menu and its
 *   structured editor opens for every system,
 * - the systems NEW page instantiates a valid blank aggregate.
 */
final class AdminGameFlowPagesTest extends WebTestCase
{
    private const PASSWORD = 'correct horse battery';

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

    /**
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
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [],
        ));

        return ['id' => $id->toString(), 'name' => $name];
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

    /**
     * @param array<string, mixed> $parameters
     */
    private function route(string $route, array $parameters = []): string
    {
        $router = static::getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\Generator\UrlGeneratorInterface);

        return $router->generate($route, $parameters);
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $users = $container->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $email = sprintf('admin-%s@integration.test', bin2hex(random_bytes(4)));
        $hashed = $hasher->hashPassword(
            new HashingSubject($email, [User::ROLE_ADMIN]),
            self::PASSWORD,
        );
        $users->save(User::register(UserId::generate(), $email, $hashed, [User::ROLE_ADMIN]));

        $crawler = $client->request('GET', '/admin/login');
        $client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => self::PASSWORD,
        ]));
        $client->followRedirect();

        return $client;
    }
}
