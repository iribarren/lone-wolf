<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Rulesets\Application\Query\ListAvailableSystemsQuery;
use App\Rulesets\Application\Query\SystemSummary;
use App\Rulesets\Infrastructure\Api\SystemResource;

/**
 * @implements ProviderInterface<SystemResource>
 */
final class SystemSummaryProvider implements ProviderInterface
{
    public function __construct(private readonly ListAvailableSystemsQuery $query)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return list<SystemResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (SystemSummary $summary): SystemResource => new SystemResource(
                $summary->systemId,
                $summary->name,
                $summary->description,
                $summary->startingStage,
                $summary->openingGuidance,
            ),
            $this->query->execute(),
        );
    }
}
