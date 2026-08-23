<?php

declare(strict_types=1);

namespace App\Oracles\Application;

use App\Oracles\Application\Command\UpdateOracleCommand;
use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\OracleEntry;

/**
 * Reweight/edit authoring path: replaces title, scope and the whole entry
 * set of an existing oracle. Storage-level scope integrity (the partial
 * unique index) still applies when re-scoping.
 */
final class UpdateOracleHandler
{
    public function __construct(private readonly OracleRepositoryInterface $oracles)
    {
    }

    /**
     * @throws \InvalidArgumentException When the oracle does not exist or the
     *                                   scope payload is inconsistent.
     */
    public function handle(UpdateOracleCommand $command): void
    {
        $oracle = $this->oracles->get($command->oracleId);

        if ($oracle === null) {
            throw new \InvalidArgumentException('Oracle not found.');
        }

        $this->oracles->save(
            $oracle
                ->withTitle($command->title)
                ->withScope($command->scopeType->scope($command->systemId))
                ->withEntries(array_map(
                    static fn (array $entry): OracleEntry => OracleEntry::place($entry['text'], $entry['weight']),
                    $command->entries,
                )),
        );
    }
}
