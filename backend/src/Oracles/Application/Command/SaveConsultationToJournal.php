<?php

declare(strict_types=1);

namespace App\Oracles\Application\Command;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\UserId;

/**
 * US4 scenario 3: persist a consulted result into the journal as an
 * oracle_result entry carrying the denormalized {oracleTitle, resultText}
 * snapshot, so history survives later oracle edits or retirement.
 */
final readonly class SaveConsultationToJournal
{
    public function __construct(
        public UserId $playerId,
        public CampaignId $campaignId,
        public string $oracleTitle,
        public string $resultText,
    ) {
    }
}
