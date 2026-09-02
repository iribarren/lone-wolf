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

    /**
     * C3: EasyAdmin titles a page from the bound entity class unless the CRUD
     * sets an entity label, so the backoffice read "Create PersistenceOracle".
     */
    public function testOraclePagesAreTitledInTheDomainLanguage(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();

        foreach ([
            $this->route('admin_dashboard_oracle_new'),
            $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]),
        ] as $url) {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful();

            $heading = $crawler->filter('h1')->text();
            self::assertStringNotContainsString('Persistence', $heading, $url);
            self::assertStringContainsString('Oracle table', $heading, $url);
        }
    }

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
     * silently empty or reword the table. Row identity is not asserted: the
     * update handler re-places every entry, so entry ids are reissued on each
     * save — nothing references them, and the entries field itself belongs to
     * prompt 05.
     */
    public function testEditingTheTitleKeepsTheEntries(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();
        $before = $this->entryContents($oracle);

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
        self::assertSame($before, $this->entryContents($oracle));
    }

    /**
     * @return list<array{text: string, weight: int}>
     */
    private function entryContents(string $oracleId): array
    {
        $decoded = json_decode($this->storedColumn('SELECT entries::text FROM oracles WHERE id = ?', $oracleId), true);
        self::assertIsArray($decoded);

        $contents = [];
        foreach ($decoded as $entry) {
            self::assertIsArray($entry);
            self::assertIsString($entry['text'] ?? null);
            self::assertIsInt($entry['weight'] ?? null);
            $contents[] = ['text' => $entry['text'], 'weight' => $entry['weight']];
        }

        return $contents;
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
