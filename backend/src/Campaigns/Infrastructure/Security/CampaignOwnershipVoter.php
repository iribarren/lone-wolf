<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Security;

use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Domain\Campaign;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * CAMPAIGN_OWNER gate (FR-019): grants only the owning player. Unknown and
 * foreign campaigns deny identically — existence is never disclosed.
 *
 * Subjects are the campaign id string taken from route variables
 * (is_granted('CAMPAIGN_OWNER', request.get('campaignId'))) or, for
 * in-process checks, the Campaign aggregate itself.
 *
 * @extends Voter<string, Campaign|string>
 */
final class CampaignOwnershipVoter extends Voter
{
    public const CAMPAIGN_OWNER = 'CAMPAIGN_OWNER';

    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly CurrentUserProviderInterface $currentUser,
    ) {
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CAMPAIGN_OWNER
            && (is_string($subject) || $subject instanceof Campaign);
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        try {
            $player = $this->currentUser->currentUserId();
        } catch (\Throwable) {
            return false;
        }

        if ($subject instanceof Campaign) {
            return $subject->isOwnedBy($player);
        }

        \assert(is_string($subject) && $subject !== '');

        $campaign = $this->campaigns->get(CampaignId::fromString($subject));

        return $campaign !== null && $campaign->isOwnedBy($player);
    }
}
