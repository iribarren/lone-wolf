<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Persistence;

use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Domain\Campaign;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class PersistenceCampaignRepository implements CampaignRepositoryInterface
{
    /** @var EntityRepository<PersistenceCampaign> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<PersistenceCampaign> $repository */
        $repository = $entityManager->getRepository(PersistenceCampaign::class);
        $this->repository = $repository;
    }

    #[\Override]
    public function add(Campaign $campaign): void
    {
        $row = $this->repository->find($campaign->id()->toString());

        if ($row === null) {
            $this->entityManager->persist(self::toRow($campaign));
            $this->entityManager->flush();

            return;
        }

        \assert($row instanceof PersistenceCampaign);
        $position = $campaign->position();
        $row->moveTo($position->stageName, $campaign->updatedAt());

        $this->entityManager->flush();
    }

    #[\Override]
    public function get(CampaignId $id): ?Campaign
    {
        $row = $this->repository->find($id->toString());

        return $row === null ? null : self::toDomain($row);
    }

    #[\Override]
    public function delete(CampaignId $id): void
    {
        $row = $this->repository->find($id->toString());

        if ($row === null) {
            return;
        }

        $this->entityManager->remove($row);
        $this->entityManager->flush();
    }

    #[\Override]
    public function ownedBy(UserId $playerId): array
    {
        /** @var list<PersistenceCampaign> $rows */
        $rows = $this->repository->findBy(['playerId' => $playerId->toString()], ['updatedAt' => 'DESC']);

        return array_map(self::toDomain(...), $rows);
    }

    private static function toRow(Campaign $campaign): PersistenceCampaign
    {
        return new PersistenceCampaign(
            $campaign->id()->toString(),
            $campaign->playerId()->toString(),
            $campaign->gameSystemId()->toString(),
            $campaign->position()->stageName,
            $campaign->createdAt(),
            $campaign->updatedAt(),
        );
    }

    private static function toDomain(PersistenceCampaign $row): Campaign
    {
        return Campaign::reconstitute(
            CampaignId::fromString($row->id()),
            UserId::fromString($row->playerId()),
            GameSystemId::fromString($row->gameSystemId()),
            new \App\Campaigns\Domain\StagePosition(GameSystemId::fromString($row->gameSystemId()), $row->stageName()),
            $row->createdAt(),
            $row->updatedAt(),
        );
    }
}
