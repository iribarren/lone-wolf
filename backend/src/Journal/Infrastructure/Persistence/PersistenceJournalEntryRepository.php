<?php

declare(strict_types=1);

namespace App\Journal\Infrastructure\Persistence;

use App\Journal\Application\Query\JournalPage;
use App\Journal\Application\Port\JournalEntryRepositoryInterface;
use App\Journal\Domain\JournalEntry;
use App\Shared\Domain\Identifier\CampaignId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class PersistenceJournalEntryRepository implements JournalEntryRepositoryInterface
{
    /** Cursor timestamps keep microseconds — same-second writes must stay strictly ordered (SC-008). */
    private const CURSOR_TIME_FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function add(JournalEntry $entry): void
    {
        $this->entityManager->persist(self::toRow($entry));
        $this->entityManager->flush();
    }

    #[\Override]
    public function page(CampaignId $campaignId, ?string $stageName, ?string $cursor, int $limit): JournalPage
    {
        $queryBuilder = $this->baseQuery($campaignId, $stageName);

        if (is_string($cursor)) {
            ['createdAt' => $createdAt, 'id' => $id] = self::parseCursor($cursor);
            $queryBuilder->andWhere('(j.createdAt < :cursorAt OR (j.createdAt = :cursorAt AND j.id < :cursorId))')
                ->setParameter('cursorAt', $createdAt, Types::DATETIMETZ_IMMUTABLE)
                ->setParameter('cursorId', $id);
        }

        // One extra row decides whether another page follows.
        $queryBuilder->setMaxResults($limit + 1);

        /** @var list<PersistenceJournalEntry> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        $nextCursor = null;
        if ($hasMore && isset($rows[$limit - 1])) {
            $last = $rows[$limit - 1];
            $nextCursor = \App\Journal\Application\ListJournalEntriesHandler::encode(
                $last->createdAt()->format(self::CURSOR_TIME_FORMAT).'|'.$last->id(),
            );
        }

        return new JournalPage(array_map(self::toDomain(...), $rows), $nextCursor);
    }

    #[\Override]
    public function deleteAllForCampaign(CampaignId $campaignId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PersistenceJournalEntry::class, 'j')
            ->where('j.campaignId = :campaignId')
            ->setParameter('campaignId', $campaignId->toString())
            ->getQuery()
            ->execute();
    }

    private static function toRow(JournalEntry $entry): PersistenceJournalEntry
    {
        $oracle = $entry->oracleSnapshot();
        $roll = $entry->rollSnapshot();

        return new PersistenceJournalEntry(
            $entry->id()->toString(),
            $entry->campaignId()->toString(),
            $entry->stageName(),
            $entry->kind(),
            $entry->narrative(),
            $oracle === null ? null : $oracle->toArray(),
            $roll === null ? null : $roll->toArray(),
            $entry->createdAt(),
        );
    }

    private static function toDomain(PersistenceJournalEntry $row): JournalEntry
    {
        return JournalEntry::reconstitute(
            \App\Shared\Domain\Identifier\JournalEntryId::fromString($row->id()),
            CampaignId::fromString($row->campaignId()),
            $row->stageName(),
            $row->kind(),
            $row->narrative(),
            $row->oracleSnapshot(),
            $row->rollSnapshot(),
            $row->createdAt(),
        );
    }

    private function baseQuery(CampaignId $campaignId, ?string $stageName): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('j')
            ->from(PersistenceJournalEntry::class, 'j')
            ->where('j.campaignId = :campaignId')
            ->setParameter('campaignId', $campaignId->toString())
            ->orderBy('j.createdAt', 'DESC')
            ->addOrderBy('j.id', 'DESC');

        if (is_string($stageName)) {
            $queryBuilder->andWhere('j.stageName = :stageName')
                ->setParameter('stageName', $stageName);
        }

        return $queryBuilder;
    }

    /**
     * @return array{createdAt: \DateTimeImmutable, id: string}
     */
    private static function parseCursor(string $rawCursor): array
    {
        $separator = strrpos($rawCursor, '|');

        if ($separator === false) {
            throw new \InvalidArgumentException('The journal pagination cursor is not valid.');
        }

        $createdAt = \DateTimeImmutable::createFromFormat(self::CURSOR_TIME_FORMAT, substr($rawCursor, 0, $separator));

        if ($createdAt === false) {
            throw new \InvalidArgumentException('The journal pagination cursor is not valid.');
        }

        return ['createdAt' => $createdAt, 'id' => substr($rawCursor, $separator + 1)];
    }
}
