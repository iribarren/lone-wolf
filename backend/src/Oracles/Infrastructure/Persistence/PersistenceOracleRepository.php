<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Persistence;

use App\Oracles\Application\Port\OracleRepositoryInterface;
use App\Oracles\Domain\Oracle;
use App\Oracles\Domain\OracleScopeType;
use App\Oracles\Domain\SystemScope;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class PersistenceOracleRepository implements OracleRepositoryInterface
{
    /** @var EntityRepository<PersistenceOracle> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<PersistenceOracle> $repository */
        $repository = $entityManager->getRepository(PersistenceOracle::class);
        $this->repository = $repository;
    }

    #[\Override]
    public function get(OracleId $id): ?Oracle
    {
        $row = $this->repository->find($id->toString());

        return $row === null ? null : $this->toDomain($row);
    }

    #[\Override]
    public function save(Oracle $oracle): void
    {
        $scope = $oracle->scope();
        $scopeType = OracleScopeType::fromScope($scope);
        $scopeSystemId = $scope instanceof SystemScope ? $scope->systemId()->toString() : null;

        $row = $this->repository->find($oracle->id()->toString());

        if ($row === null) {
            $row = new PersistenceOracle(
                $oracle->id()->toString(),
                $oracle->title(),
                $scopeType->value,
                $scopeSystemId,
                OracleJsonMapper::entriesToPayload($oracle->entries()),
            );

            $this->entityManager->persist($row);
            $this->entityManager->flush();

            return;
        }

        $row->replace(
            $oracle->title(),
            $scopeType->value,
            $scopeSystemId,
            OracleJsonMapper::entriesToPayload($oracle->entries()),
        );

        $this->entityManager->flush();
    }

    #[\Override]
    public function visibleTo(GameSystemId $systemId): array
    {
        /** @var list<PersistenceOracle> $rows */
        $rows = $this->repository->createQueryBuilder('o')
            ->where('o.scopeType = :global')
            ->orWhere('o.scopeSystemId = :systemId')
            ->setParameter('global', OracleScopeType::Global->value)
            ->setParameter('systemId', $systemId->toString())
            ->orderBy('o.title', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map($this->toDomain(...), $rows);
    }

    private function toDomain(PersistenceOracle $row): Oracle
    {
        $scope = OracleScopeType::from($row->scopeType())->scope(
            $row->scopeSystemId() === null ? null : GameSystemId::fromString($row->scopeSystemId()),
        );

        return Oracle::reconstitute(
            OracleId::fromString($row->id()),
            $row->title(),
            $scope,
            OracleJsonMapper::entriesFromPayload($row->entries()),
        );
    }
}
