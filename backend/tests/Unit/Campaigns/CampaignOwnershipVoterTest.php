<?php

declare(strict_types=1);

namespace App\Tests\Unit\Campaigns;

use App\Campaigns\Application\Port\CampaignRepositoryInterface;
use App\Campaigns\Domain\Campaign;
use App\Campaigns\Domain\StagePosition;
use App\Campaigns\Infrastructure\Security\CampaignOwnershipVoter;
use App\Shared\Application\CurrentUserProviderInterface;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * CAMPAIGN_OWNER gate (FR-019): grants only the owning player; unknown ids
 * deny silently instead of disclosing existence.
 */
final class CampaignOwnershipVoterTest extends TestCase
{
    private const OWNER = '11111111-1111-4111-8111-111111111111';

    private const STRANGER = '22222222-2222-4222-8222-222222222222';

    private const CAMPAIGN = '33333333-3333-4333-8333-333333333333';

    public function testGrantsTheOwningPlayer(): void
    {
        $voter = $this->voter(self::OWNER);

        $granted = $voter->vote($this->token(), self::CAMPAIGN, ['CAMPAIGN_OWNER']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $granted);
    }

    public function testDeniesAForeignPlayerAndUnknownIdsIdentically(): void
    {
        $voter = $this->voter(self::STRANGER);

        $foreign = $voter->vote($this->token(), self::CAMPAIGN, ['CAMPAIGN_OWNER']);
        $unknown = $voter->vote($this->token(), '44444444-4444-4444-8444-444444444444', ['CAMPAIGN_OWNER']);

        // Same deny outcome — existence is never disclosed (FR-019).
        self::assertSame(VoterInterface::ACCESS_DENIED, $foreign);
        self::assertSame(VoterInterface::ACCESS_DENIED, $unknown);
    }

    public function testDeniesWhenNobodyIsAuthenticated(): void
    {
        $provider = new class implements CurrentUserProviderInterface {
            #[\Override]
            public function currentUserId(): UserId
            {
                throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
            }
        };

        $repo = new InMemoryCampaignFixture([]);

        $voter = new CampaignOwnershipVoter($repo, $provider);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), self::CAMPAIGN, ['CAMPAIGN_OWNER']),
        );
    }

    public function testIgnoresOtherAttributes(): void
    {
        $voter = $this->voter(self::OWNER);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token(), self::CAMPAIGN, ['EDIT']),
        );
    }

    private function token(): TokenInterface
    {
        return new NullToken();
    }

    private function voter(string $currentPlayer): CampaignOwnershipVoter
    {
        $campaign = Campaign::start(
            CampaignId::fromString(self::CAMPAIGN),
            UserId::fromString(self::OWNER),
            new StagePosition(GameSystemId::generate(), 'Scene'),
            new \DateTimeImmutable('2026-08-23T10:00:00Z'),
        );

        return new CampaignOwnershipVoter(
            new InMemoryCampaignFixture([$campaign]),
            new FixedPlayerProvider(UserId::fromString($currentPlayer)),
        );
    }
}

/**
 * @internal test fixture
 */
final class InMemoryCampaignFixture implements CampaignRepositoryInterface
{
    /** @var array<string, Campaign> */
    private array $campaigns = [];

    /**
     * @param list<Campaign> $seeded
     */
    public function __construct(array $seeded)
    {
        foreach ($seeded as $campaign) {
            $this->campaigns[$campaign->id()->toString()] = $campaign;
        }
    }

    #[\Override]
    public function add(Campaign $campaign): void
    {
        $this->campaigns[$campaign->id()->toString()] = $campaign;
    }

    #[\Override]
    public function get(CampaignId $id): ?Campaign
    {
        return $this->campaigns[$id->toString()] ?? null;
    }

    #[\Override]
    public function delete(CampaignId $id): void
    {
        unset($this->campaigns[$id->toString()]);
    }

    #[\Override]
    public function ownedBy(UserId $playerId): array
    {
        return array_values(array_filter(
            $this->campaigns,
            static fn (Campaign $campaign): bool => $campaign->isOwnedBy($playerId),
        ));
    }
}

/**
 * @internal test fixture
 */
final class FixedPlayerProvider implements CurrentUserProviderInterface
{
    public function __construct(private readonly UserId $player)
    {
    }

    #[\Override]
    public function currentUserId(): UserId
    {
        return $this->player;
    }
}
