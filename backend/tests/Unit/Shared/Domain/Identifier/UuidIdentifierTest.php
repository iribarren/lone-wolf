<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Identifier;

use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\GameSystemId;
use PHPUnit\Framework\TestCase;

final class UuidIdentifierTest extends TestCase
{
    private const VALID = '0b6f5e2a-9c1d-4e8f-a3b2-7d6c5e4f3a21';

    public function testFromStringNormalisesCaseAndRoundTrips(): void
    {
        $id = CampaignId::fromString(strtoupper(self::VALID));

        self::assertSame(self::VALID, $id->toString());
        self::assertSame(self::VALID, (string) $id);
    }

    public function testGenerateProducesDistinctValidIdentifiers(): void
    {
        $a = CampaignId::generate();
        $b = CampaignId::generate();

        self::assertNotSame($a->toString(), $b->toString());
        self::assertTrue($a->equals(CampaignId::fromString($a->toString())));
    }

    public function testEqualityRequiresSameValueAndSameType(): void
    {
        $campaign = CampaignId::fromString(self::VALID);
        $sameCampaign = CampaignId::fromString(self::VALID);
        $systemWithSameValue = GameSystemId::fromString(self::VALID);

        self::assertTrue($campaign->equals($sameCampaign));
        // Same underlying value but different identifier type is never equal.
        self::assertFalse($campaign->equals($systemWithSameValue));
    }

    public function testRejectsNonUuidStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CampaignId::fromString('not-a-uuid');
    }

    public function testRejectsWrongUuidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CampaignId::fromString('0b6f5e2a-9c1d-1e8f-a3b2-7d6c5e4f3a21');
    }
}
