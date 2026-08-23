<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Port;

use App\Rulesets\Domain\GameSystem;
use App\Shared\Domain\Identifier\GameSystemId;

interface RulesetRepositoryInterface
{
    public function get(GameSystemId $id): ?GameSystem;

    public function findByName(string $name): ?GameSystem;

    /** @return list<GameSystem> */
    public function all(): array;

    /**
     * Persists the aggregate snapshot; concurrent supersede conflicts surface
     * as {@see \Doctrine\ORM\OptimisticLockException}.
     */
    public function save(GameSystem $system): void;
}
