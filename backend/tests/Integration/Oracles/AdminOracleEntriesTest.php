<?php

declare(strict_types=1);

namespace App\Tests\Integration\Oracles;

use App\Oracles\Application\Command\CreateOracleCommand;
use App\Oracles\Application\CreateOracleHandler;
use App\Oracles\Domain\OracleScopeType;
use App\Tests\Integration\Support\SignsInAsAdmin;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A4: the oracle CRUD read the entry set on save but offered no field to
 * author it. These cover the editor end to end against the real backoffice —
 * create, reopen, reweight, delete a row, and the refusal — plus the list and
 * detail pages, which an EasyAdmin array field over a jsonb column has already
 * broken once (08a16c5).
 */
final class AdminOracleEntriesTest extends WebTestCase
{
    use SignsInAsAdmin;

    /** EasyAdmin names the CRUD form after the bound entity class. */
    private const FORM = 'PersistenceOracle';

    public function testTheNewFormExposesTheEntriesEditor(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', $this->route('admin_dashboard_oracle_new'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Result entries');
        // One prototype: the "add row" template the collection renders.
        self::assertSelectorCount(1, '[data-prototype]');
    }

    public function testCreatingAnOracleWithWeightedEntriesPersistsThem(): void
    {
        $client = $this->adminClient();
        $title = self::uniqueTitle();

        $this->submitOracleForm($client, $this->route('admin_dashboard_oracle_new'), [
            'title' => $title,
            'scopeType' => 'global',
            'scopeSystemId' => '',
            'entries' => [
                ['text' => 'Clear skies.', 'weight' => '3'],
                ['text' => 'Overcast.', 'weight' => '2'],
                ['text' => 'Storm rolling in.', 'weight' => '1'],
            ],
        ]);

        self::assertSaveSucceeded($client);
        self::assertSame([
            ['text' => 'Clear skies.', 'weight' => 3],
            ['text' => 'Overcast.', 'weight' => 2],
            ['text' => 'Storm rolling in.', 'weight' => 1],
        ], $this->entryContents($this->idOf($title)));
    }

    public function testReopeningTheOracleShowsTheStoredRows(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();

        $crawler = $client->request('GET', $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]));
        self::assertResponseIsSuccessful();

        $values = $crawler->filter(sprintf('#edit-%s-form', self::FORM))->form()->getPhpValues();
        $entity = is_array($values[self::FORM] ?? null) ? $values[self::FORM] : [];

        self::assertSame([
            ['text' => 'Clear skies', 'weight' => '2'],
            ['text' => 'Sudden storm', 'weight' => '1'],
        ], $entity['entries'] ?? null);
    }

    public function testChangingOneWeightChangesOnlyThatWeight(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();

        $this->submitOracleForm($client, $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]), [
            'entries' => [
                ['text' => 'Clear skies', 'weight' => '5'],
                ['text' => 'Sudden storm', 'weight' => '1'],
            ],
        ]);

        self::assertSaveSucceeded($client);
        self::assertSame([
            ['text' => 'Clear skies', 'weight' => 5],
            ['text' => 'Sudden storm', 'weight' => 1],
        ], $this->entryContents($oracle));
    }

    public function testRemovingARowRemovesExactlyThatEntry(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();

        // A browser posts the surviving rows keeping their original indices.
        $this->submitOracleForm($client, $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]), [
            'entries' => [1 => ['text' => 'Sudden storm', 'weight' => '1']],
        ]);

        self::assertSaveSucceeded($client);
        self::assertSame([['text' => 'Sudden storm', 'weight' => 1]], $this->entryContents($oracle));
    }

    public function testAZeroWeightIsRefusedAndNothingIsPersisted(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();
        $before = $this->entryContents($oracle);

        $this->submitOracleForm($client, $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]), [
            'entries' => [
                ['text' => 'Clear skies', 'weight' => '0'],
                ['text' => 'Sudden storm', 'weight' => '1'],
            ],
        ]);

        // The aggregate's own words, surfaced as a flash rather than a crash.
        $body = $client->getResponse()->isRedirect() ? $client->followRedirect()->text() : $this->responseText($client);
        self::assertStringContainsString('weights must be positive integers', $body);
        self::assertSame($before, $this->entryContents($oracle), 'A refused edit must not touch the stored table.');
    }

    /**
     * Entry ids are deliberately NOT stable across an edit: UpdateOracleCommand
     * carries {text, weight} with no id slot, UpdateOracleHandler re-places
     * every entry, and OracleEntry::reconstitute() is internal to the
     * repository — the domain keeps identity to itself and documents
     * withEntries() as replacing the whole set.
     *
     * Nothing references these ids. They surface only in the transient consult
     * response; a journalled consultation stores the table's title and the
     * result text. Pinned here so that reading a stale id somewhere new, or
     * deciding entries should keep their identity, is a deliberate change.
     */
    public function testEditingOneRowReissuesEveryEntryId(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();
        $before = $this->entryIds($oracle);

        $this->submitOracleForm($client, $this->route('admin_dashboard_oracle_edit', ['entityId' => $oracle]), [
            'entries' => [
                ['text' => 'Clear skies, reworded', 'weight' => '2'],
                ['text' => 'Sudden storm', 'weight' => '1'],
            ],
        ]);

        self::assertSaveSucceeded($client);

        $after = $this->entryIds($oracle);
        self::assertCount(2, $after);
        self::assertSame([], array_intersect($before, $after), 'The untouched row is reissued too.');
    }

    public function testTheListAndDetailPagesRenderWithEntries(): void
    {
        $client = $this->adminClient();
        $oracle = $this->createOracle();
        $title = $this->storedColumn('SELECT title FROM oracles WHERE id = ?', $oracle);

        // The table paginates across the fixture rows; EA's index search
        // narrows to the freshly created jsonb-backed row.
        $client->request('GET', '/admin/oracle?query='.urlencode($title));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $title);

        $client->request('GET', $this->route('admin_dashboard_oracle_detail', ['entityId' => $oracle]));
        self::assertResponseIsSuccessful();
    }

    /**
     * Posts the CRUD form the way a browser does. The entries collection is
     * written straight into the form's own payload — DomCrawler cannot add
     * rows to a prototype-driven collection.
     *
     * @param array<string, mixed> $payload fields to override
     */
    private function submitOracleForm(KernelBrowser $client, string $url, array $payload): void
    {
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[name="%s"]', self::FORM))->form();

        $values = $form->getPhpValues();
        $entity = is_array($values[self::FORM] ?? null) ? $values[self::FORM] : [];
        $values[self::FORM] = array_replace($entity, $payload);

        $client->request($form->getMethod(), $form->getUri(), $values);
    }

    private static function assertSaveSucceeded(KernelBrowser $client): void
    {
        $status = $client->getResponse()->getStatusCode();

        self::assertLessThan(
            400,
            $status,
            sprintf('Saving the oracle form returned HTTP %d instead of persisting.', $status),
        );
    }

    private function responseText(KernelBrowser $client): string
    {
        $content = $client->getResponse()->getContent();

        return $content === false ? '' : $content;
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

    /**
     * @return list<string>
     */
    private function entryIds(string $oracleId): array
    {
        $decoded = json_decode($this->storedColumn('SELECT entries::text FROM oracles WHERE id = ?', $oracleId), true);
        self::assertIsArray($decoded);

        $ids = [];
        foreach ($decoded as $entry) {
            self::assertIsArray($entry);
            self::assertIsString($entry['id'] ?? null);
            $ids[] = $entry['id'];
        }

        return $ids;
    }

    private function idOf(string $title): string
    {
        $id = $this->storedColumn('SELECT id FROM oracles WHERE title = ?', $title);
        self::assertNotSame('', $id, sprintf('No oracle titled "%s" was persisted.', $title));

        return $id;
    }

    private function createOracle(): string
    {
        $handler = static::getContainer()->get(CreateOracleHandler::class);
        \assert($handler instanceof CreateOracleHandler);

        return $handler->handle(new CreateOracleCommand(
            self::uniqueTitle(),
            OracleScopeType::Global,
            null,
            [
                ['text' => 'Clear skies', 'weight' => 2],
                ['text' => 'Sudden storm', 'weight' => 1],
            ],
        ))->toString();
    }

    private static function uniqueTitle(): string
    {
        return 'Entries editor '.bin2hex(random_bytes(4));
    }
}
