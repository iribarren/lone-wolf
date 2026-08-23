<?php

declare(strict_types=1);

namespace App\Oracles\Application;

use App\Oracles\Application\Command\CreateOracleCommand;
use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleEntry;
use App\Shared\Domain\Identifier\OracleId;

/**
 * FR-007: authors a titled oracle with weighted entries, scoped globally or
 * to exactly one game system (FR-008).
 */
final class CreateOracleHandler
{
    public function __construct(private readonly OracleRepositoryInterface $oracles)
    {
    }

    /**
     * @throws \InvalidArgumentException When the scope payload is inconsistent.
     * @throws \Doctrine\DBAL\Exception\UniqueConstraintViolationException When
     *                                     the target system already owns its scoped table.
     */
    public function handle(CreateOracleCommand $command): OracleId
    {
        $oracle = Oracle::start(
            OracleId::generate(),
            $command->title,
            $command->scopeType->scope($command->systemId),
            self::entries($command),
        );

        $this->oracles->save($oracle);

        return $oracle->id();
    }

    /**
     * @param CreateOracleCommand $command
     * @return list<OracleEntry>
     */
    private static function entries(CreateOracleCommand $command): array
    {
        return array_map(
            static fn (array $entry): OracleEntry => OracleEntry::place($entry['text'], $entry['weight']),
            $command->entries,
        );
    }
}
