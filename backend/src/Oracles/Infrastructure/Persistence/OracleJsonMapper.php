<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Persistence;

use App\Oracles\Domain\OracleEntry;
use App\Shared\Domain\Identifier\OracleEntryId;

/**
 * Serializes the oracle's entry set to/from its jsonb row representation.
 *
 * @phpstan-type OracleEntryPayload array{id: string, text: string, weight: int}
 */
final class OracleJsonMapper
{
    /**
     * @param list<OracleEntry> $entries
     * @return list<OracleEntryPayload>
     */
    public static function entriesToPayload(array $entries): array
    {
        return array_map(
            static fn (OracleEntry $entry): array => [
                'id' => $entry->id()->toString(),
                'text' => $entry->text(),
                'weight' => $entry->weight(),
            ],
            $entries,
        );
    }

    /**
     * @param list<OracleEntryPayload> $payload
     * @return list<OracleEntry>
     */
    public static function entriesFromPayload(array $payload): array
    {
        return array_map(
            static fn (array $entry): OracleEntry => OracleEntry::reconstitute(
                OracleEntryId::fromString((string) ($entry['id'] ?? '')),
                (string) ($entry['text'] ?? ''),
                (int) ($entry['weight'] ?? 0),
            ),
            array_values($payload),
        );
    }
}
