<?php

declare(strict_types=1);

namespace App\Campaigns\Application\Port;

use App\Campaigns\Domain\Campaign;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * Aggregate persistence for campaigns (Constitution I port). Implemented by
 * Doctrine in Infrastructure.
 */
interface CampaignRepositoryInterface
{
    /** Inserts or updates the aggregate snapshot. */
    public function add(Campaign $campaign): void;

    public function get(CampaignId $id): ?Campaign;

    /** Hard delete — irreversible (FR-020). */
    public function delete(CampaignId $id): void;

    /** FR-019 — reads are always scoped to the owning player. @return list<Campaign> */
    public function ownedBy(UserId $playerId): array;
}
