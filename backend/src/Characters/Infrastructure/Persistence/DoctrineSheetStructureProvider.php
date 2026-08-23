<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Persistence;

use App\Characters\Application\Port\SheetStructureProviderInterface;
use App\Characters\Domain\SheetField;
use App\Characters\Domain\SheetSchema;
use App\Shared\Domain\Identifier\GameSystemId;
use Doctrine\DBAL\Connection;

/**
 * Reads the Rulesets-owned game_systems row and translates its
 * sheet_structure payload into the Characters-owned SheetSchema — no
 * Rulesets class is imported (Constitution II; shared storage, shared
 * never model).
 */
final readonly class DoctrineSheetStructureProvider implements SheetStructureProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function forSystem(GameSystemId $gameSystemId): ?SheetSchema
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->executeQuery(
            'SELECT sheet_structure FROM game_systems WHERE id = :id',
            ['id' => $gameSystemId->toString()],
        )->fetchAssociative();

        if ($row === false || !is_string($row['sheet_structure'] ?? null)) {
            return null;
        }

        $payload = json_decode($row['sheet_structure'], true);

        if (!is_array($payload)) {
            throw new \RuntimeException('A stored sheet structure has a malformed payload.');
        }

        // The stored envelope carries {version, fields}; tolerate a bare
        // field list for structures authored before versioning.
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : (array_is_list($payload) ? $payload : []);
        $version = is_int($payload['version'] ?? null) ? $payload['version'] : 1;

        if ($fields === []) {
            return null;
        }

        return new SheetSchema(
            $version,
            array_values(array_map(
                static fn (mixed $field): SheetField => SheetField::fromPayload(
                    is_array($field) ? $field : [],
                ),
                $fields,
            )),
        );
    }
}
