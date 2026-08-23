<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Persistence;

use App\Rulesets\Application\Port\RulesetRepositoryInterface;
use App\Rulesets\Domain\GameSystem;
use App\Shared\Domain\Identifier\GameSystemId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @phpstan-import-type SheetPayload from RulesetJsonMapper
 */
final class PersistenceRulesetRepository implements RulesetRepositoryInterface
{
    /** @var EntityRepository<PersistenceGameSystem> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<PersistenceGameSystem> $repository */
        $repository = $entityManager->getRepository(PersistenceGameSystem::class);
        $this->repository = $repository;
    }

    #[\Override]
    public function get(GameSystemId $id): ?GameSystem
    {
        $row = $this->repository->find($id->toString());

        return $row === null ? null : $this->toDomain($row);
    }

    #[\Override]
    public function findByName(string $name): ?GameSystem
    {
        $row = $this->repository->findOneBy(['name' => $name]);

        return $row === null ? null : $this->toDomain($row);
    }

    #[\Override]
    public function all(): array
    {
        return array_map($this->toDomain(...), $this->repository->findBy([], ['name' => 'ASC']));
    }

    #[\Override]
    public function save(GameSystem $system): void
    {
        $row = $this->repository->find($system->id()->toString());

        if ($row === null) {
            $flowPayload = RulesetJsonMapper::flowToPayload($system->flowDefinition());

            $row = new PersistenceGameSystem(
                $system->id()->toString(),
                $system->name(),
                $system->description(),
                $system->status(),
                $flowPayload,
                self::sheetPayloadOrNull($system),
            );

            $this->entityManager->persist($row);
            $this->entityManager->flush();

            return;
        }

        $row->replace(
            $system->name(),
            $system->description(),
            $system->status(),
            RulesetJsonMapper::flowToPayload($system->flowDefinition()),
            self::sheetPayloadOrNull($system),
        );

        $this->entityManager->flush();
    }

    /**
     * @return SheetPayload|null
     */
    private static function sheetPayloadOrNull(GameSystem $system): ?array
    {
        $structure = $system->sheetStructure();

        return $structure === null ? null : RulesetJsonMapper::sheetToPayload($structure);
    }

    private function toDomain(PersistenceGameSystem $row): GameSystem
    {
        $sheetPayload = $row->sheetStructure();

        return GameSystem::reconstitute(
            GameSystemId::fromString($row->id()),
            $row->name(),
            $row->description(),
            $row->status(),
            RulesetJsonMapper::flowFromPayload($row->flowDefinition()),
            $sheetPayload === null ? null : RulesetJsonMapper::sheetFromPayload($sheetPayload),
        );
    }
}
