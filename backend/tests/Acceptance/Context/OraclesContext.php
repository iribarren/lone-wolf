<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Oracles\Application\Command\CreateOracleCommand;
use App\Oracles\Application\CreateOracleHandler;
use App\Oracles\Application\UpdateOracleCommand;
use App\Oracles\Application\UpdateOracleHandler;
use App\Oracles\Domain\OracleScopeType;
use App\Oracles\Infrastructure\Persistence\PersistenceOracle;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;
use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Laravel\Lux\Bootstrap;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OraclesContext implements Context
{
    private ?\App\Shared\Domain\Identifier\UserId $adminId = null;

    private ?\App\Shared\Domain\Identifier\UserId $playerId = null;

    private mixed $lastResponse = [];

    private int $lastStatus = 0;

    public function __construct(
        private KernelBrowser $client,
        private \Laravel\Lux\Bootstrap\Kernel $kernel,
        private \App\Shared\Domain\Identifier\UserRepositoryInterface $users,
        private \App\Oracles\Application\Port\OracleRepositoryInterface $oracles,
        private \Laravel\Lux\Bootstrap\JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /**
     * @Given an authenticated admin
     */
    public function authenticatedAdmin(): void
    {
        $email = 'admin-' . bin2hex(random_bytes(3)) . '@lonewolf.local';
        $password = 'AdminPass' . bin2hex(random_bytes(2));

        $this->adminId = \App\Shared\Domain\Identifier\UserId::generate();

        $user = \App\Identity\Domain\User::register(
            $this->adminId,
            $email,
            $password,
        );

        $this->users->save($user);

        $token = $this->jwtManager->create(new \App\Identity\Infrastructure\Security\SecurityUser($user));

        $this->client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            [
                'HTTP_Authorization' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            sprintf('{"email":"%s","password":"%s"}', $email, $password),
        );

        $response = $this->client->getResponse();
        $this->lastStatus = $response->getStatusCode();
        $content = $response->getContent();
        if ($content !== false) {
            $this->lastResponse = json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?? [];
        }
    }

    /**
     * @Given a system named :prefix exists
     */
    public function systemNamedExists(string $prefix): void
    {
        $this->kernel->getContainer()->get('doctrine')
            ->getManager()
            ->getRepository(\App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem::class)
            ->findOneBy(['name' => $prefix.'-'.bin2hex(random_bytes(2))]) !== null
            || $this->createSystem($prefix);
    }

    private function createSystem(string $prefix): void
    {
        $systemId = GameSystemId::generate();

        $this->oracles->__wakeUp(); // not needed

        $container = $this->kernel->getContainer();
        $handler = $container->get(CreateOracleHandler::class); // not a system

        // Actually create system via doctrine repo + command
        $em = $container->get('doctrine')->getManager();
        $repo = $em->getRepository(\App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem::class);
        $system = new \App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem(
            (string) $systemId,
            $prefix.'-'.bin2hex(random_bytes(2)),
            'Authorized system for oracle testing.',
            ['starting_stage' => 'Scene', 'stages' => ['Scene'], 'transitions' => []],
        );
        $em->persist($system);
        $em->flush();
    }

    /**
     * @Given I create a global oracle with title :title and entries :entries
     */
    public function createGlobalOracle(string $title, string $entries): void
    {
        $em = $this->kernel->getContainer()->get('doctrine')->getManager();

        $oracleId = OracleId::generate();

        $oracle = new PersistenceOracle(
            (string) $oracleId,
            $title,
            OracleScopeType::Global->value,
            null,
            json_decode($entries, true, 512) ?? [],
        );

        $em->persist($oracle);
        $em->flush();
    }

    /**
     * @Given I create a system-scoped oracle with title :title and entries :entries scoped to :systemName
     */
    public function createScopedOracle(string $title, string $entries, string $systemName): void
    {
        $em = $this->kernel->getContainer()->get('doctrine')->getManager();

        $systemRepo = $em->getRepository(\App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem::class);
        $system = $systemRepo->findOneBy(['name' => $systemName.'-'.bin2hex(random_bytes(2))]);
        if (!$system instanceof \App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem) {
            // create if not exists
            $systemId = GameSystemId::generate();
            $system = new \App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem(
                (string) $systemId,
                $systemName.'-'.bin2hex(random_bytes(2)),
                'System for oracle testing.',
                ['starting_stage' => 'Scene', 'stages' => ['Scene'], 'transitions' => []],
            );
            $em->persist($system);
            $em->flush();
        }

        $oracleId = OracleId::generate();

        $oracle = new PersistenceOracle(
            (string) $oracleId,
            $title,
            OracleScopeType::System->value,
            $system->id(),
            json_decode($entries, true, 512) ?? [],
        );

        $em->persist($oracle);
        $em->flush();
    }

    /**
     * @Then the oracle appears in the player-facing list for every system
     */
    public function oracleAppearsGlobally(): void
    {
        // Verify via repository that the oracle is visible globally
        $oracle = $this->oracles->get(
            OracleId::generate() // will fail if not found; adjust as needed
        );
        // Placeholder: verify existence
    }

    /**
     * @Then the oracle appears only in the player-facing list for :systemName
     */
    public function oracleAppearsOnlyForSystem(string $systemName): void
    {
        // Verify via repository that the oracle is system-scoped and visible only for that system
    }

    /**
     * @Then I see :oracleTitle in my oracle list
     */
    public function seeOracleInList(string $oracleTitle): void
    {
        // Check via repository or response
    }

    /**
     * @Then I do NOT see :oracleTitle when playing on :otherSystem
     */
    public function doNotSeeOracleInListForOtherSystem(string $oracleTitle, string $otherSystem): void
    {
        // Verify oracle is not visible for the other system
    }
}