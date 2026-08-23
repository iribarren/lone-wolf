<?php

declare(strict_types=1);

namespace App\Characters\Application\Port;

use App\Characters\Domain\Character;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\CharacterId;

/**
 * Aggregates port of the Characters context (Constitution I). Deleting a
 * campaign removes its cast through the storage-level FK cascade (T080).
 */
interface CharacterRepositoryInterface
{
    public function add(Character $character): void;

    public function get(CharacterId $id): ?Character;

    /** @return list<Character> */
    public function listForCampaign(CampaignId $campaignId): array;
}
