<?php

declare(strict_types=1);

namespace App\Tests\Integration\Oracles;

use App\Oracles\Application\Command\CreateOracleCommand;
use App\Oracles\Application\CreateOracleHandler;
use App\Oracles\Domain\OracleScopeType;
use App\Tests\Integration\Support\SignsInAsAdmin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A6: the oracle CRUD rendered a correct edit form and then refused to save
 * it — Symfony's DataMapper writes every mapped field back through
 * PropertyAccess, which mistook the `title()` accessor for a mutator.
 */
final class AdminOracleSaveTest extends WebTestCase
{
    use SignsInAsAdmin;

    public function testEditingTheTitlePersistsIt(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();

        $crawler = $client->request(
            'GET',
            $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]),
        );
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form();
        $form['PersistenceOracle[title]'] = 'Renamed weather table';
        $client->submit($form);

        $status = $client->getResponse()->getStatusCode();
        self::assertLessThan(400, $status, sprintf('Saving the oracle form returned HTTP %d.', $status));

        self::assertSame(
            'Renamed weather table',
            $this->storedColumn('SELECT title FROM oracles WHERE id = ?', $oracle),
        );
    }

    /**
     * Entries are not on the form yet (A4/prompt 05); a title edit must not
     * silently empty the table.
     */
    public function testEditingTheTitleKeepsTheEntries(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();
        $before = $this->storedColumn('SELECT entries::text FROM oracles WHERE id = ?', $oracle);

        $crawler = $client->request(
            'GET',
            $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]),
        );
        $form = $crawler->selectButton('Save changes')->form();
        $form['PersistenceOracle[title]'] = 'Still weather';
        $client->submit($form);

        $status = $client->getResponse()->getStatusCode();
        self::assertLessThan(400, $status, sprintf('Saving the oracle form returned HTTP %d.', $status));
        self::assertSame('Still weather', $this->storedColumn('SELECT title FROM oracles WHERE id = ?', $oracle));
        self::assertSame($before, $this->storedColumn('SELECT entries::text FROM oracles WHERE id = ?', $oracle));
    }

    private function createOracle(): string
    {
        $handler = static::getContainer()->get(CreateOracleHandler::class);
        \assert($handler instanceof CreateOracleHandler);

        return $handler->handle(new CreateOracleCommand(
            'Weather '.bin2hex(random_bytes(4)),
            OracleScopeType::Global,
            null,
            [
                ['text' => 'Clear skies', 'weight' => 2],
                ['text' => 'Sudden storm', 'weight' => 1],
            ],
        ))->toString();
    }
}
