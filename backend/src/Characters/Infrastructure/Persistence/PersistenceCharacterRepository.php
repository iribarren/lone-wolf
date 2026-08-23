<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Persistence;

use App\Characters\Application\Port\CharacterRepositoryInterface;
use App\Characters\Domain\AttributesMap;
use App\Characters\Domain\Character;
use App\Characters\Domain\CharacterKind;
use App\Characters\Domain\ReviewStatus;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\CharacterId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class PersistenceCharacterRepository implements CharacterRepositoryInterface
{
    /** @var EntityRepository<PersistenceCharacter> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<PersistenceCharacter> $repository */
        $repository = $entityManager->getRepository(PersistenceCharacter::class);
        $this->repository = $repository;
    }

    #[\Override]
    public function add(Character $character): void
    {
        $row = $this->repository->find($character->id()->toString());

        $payload = [
            $character->name(),
            $character->attributes()->toArray(),
            $character->validatedStructureVersion(),
            $character->reviewStatus()->value,
            $character->driftIssues(),
        ];

        if ($row === null) {
            $this->entityManager->persist(new PersistenceCharacter(
                $character->id()->toString(),
                $character->campaignId()->toString(),
                $character->kind()->value,
                ...$payload,
            ));
            $this->entityManager->flush();

            return;
        }

        \assert($row instanceof PersistenceCharacter);
        $row->replace(...$payload);
        $this->entityManager->flush();
    }

    #[\Override]
    public function get(CharacterId $id): ?Character
    {
        $row = $this->repository->find($id->toString());

        return $row === null ? null : self::toDomain($row);
    }

    #[\Override]
    public function listForCampaign(CampaignId $campaignId): array
    {
        /** @var list<PersistenceCharacter> $rows */
        $rows = $this->repository->findBy(['campaignId' => $campaignId->toString()], ['name' => 'ASC']);

        return array_map(self::toDomain(...), $rows);
    }

    private static function toDomain(PersistenceCharacter $row): Character
    {
        return Character::reconstitute(
            CharacterId::fromString($row->id()),
            CampaignId::fromString($row->campaignId()),
            CharacterKind::fromString($row->kind()),
            $row->name(),
            AttributesMap::fromArray($row->attributes()),
            $row->validatedStructureVersion(),
            ReviewStatus::from($row->reviewStatus()),
            $row->driftIssues(),
        );
    }
}
