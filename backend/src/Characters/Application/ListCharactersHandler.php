<?php

declare(strict_types=1);

namespace App\Characters\Application;

use App\Campaigns\Application\OwnedCampaignFetcher;
use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Characters\Application\Port\CharacterRepositoryInterface;
use App\Characters\Application\Port\SheetStructureProviderInterface;
use App\Characters\Application\Query\CharacterData;
use App\Characters\Application\Query\ListCharactersQuery;
use App\Characters\Application\Query\SheetFieldView;
use App\Characters\Application\Query\SheetStructureView;
use App\Characters\Domain\DriftDetector;
use App\Characters\Domain\ReviewStatus;

/**
 * GET /api/campaigns/{id}/characters (FR-019 owner-scoped). Each character
 * is projected against the CURRENT structure: stored data that no longer
 * conforms surfaces as flagged_for_review with drift issues — read-only,
 * never persisted over the stored aggregate (FR-025).
 */
final readonly class ListCharactersHandler
{
    public function __construct(
        private CharacterRepositoryInterface $characters,
        private SheetStructureProviderInterface $sheets,
        private CampaignRepositoryInterface $campaigns,
    ) {
    }

    /** @return list<CharacterData> */
    public function handle(ListCharactersQuery $query): array
    {
        $campaign = (new OwnedCampaignFetcher($this->campaigns))->fetch($query->campaignId, $query->playerId);

        $schema = $this->sheets->forSystem($campaign->gameSystemId());
        $detector = new DriftDetector();

        return array_map(
            fn ($character): CharacterData => new CharacterData(
                $character->id()->toString(),
                $character->kind()->value,
                $character->name(),
                $character->attributes()->toArray(),
                $character->validatedStructureVersion(),
                self::projectedStatus($detector, $schema, $character),
                self::projectedIssues($detector, $schema, $character),
                self::structure($schema),
            ),
            $this->characters->listForCampaign($query->campaignId),
        );
    }

    private static function projectedStatus(DriftDetector $detector, ?\App\Characters\Domain\SheetSchema $schema, \App\Characters\Domain\Character $character): string
    {
        if ($schema === null) {
            return $character->reviewStatus()->value;
        }

        return $detector->driftIssues($character->kind(), $schema, $character->attributes()) === []
            ? ReviewStatus::Clean->value
            : ReviewStatus::FlaggedForReview->value;
    }

    /**
     * @return list<string>
     */
    private static function projectedIssues(DriftDetector $detector, ?\App\Characters\Domain\SheetSchema $schema, \App\Characters\Domain\Character $character): array
    {
        if ($schema === null) {
            return $character->driftIssues();
        }

        return $detector->driftIssues($character->kind(), $schema, $character->attributes());
    }

    private static function structure(?\App\Characters\Domain\SheetSchema $schema): ?SheetStructureView
    {
        if ($schema === null) {
            return null;
        }

        return new SheetStructureView(
            $schema->version,
            array_values(array_map(
                static fn ($field): SheetFieldView => new SheetFieldView(
                    $field->key(),
                    $field->label(),
                    $field->type(),
                    $field->isRequiredForPc(),
                    $field->isRequiredForNpc(),
                    $field->options(),
                ),
                $schema->fields(),
            )),
        );
    }
}
