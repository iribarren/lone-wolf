<?php

declare(strict_types=1);

namespace App\Campaigns\Application;

use App\Shared\Domain\Identifier\GameSystemId;

/**
 * FR-012 refusals when a campaign cannot be started on the requested
 * system: either the id is unknown or the system is deactivated.
 */
final class SystemNotPlayableException extends \DomainException
{
    public static function unknown(GameSystemId $gameSystemId): self
    {
        return new self(sprintf('Game system "%s" is unknown.', $gameSystemId->toString()));
    }

    public static function inactive(string $systemName): self
    {
        return new self(sprintf('Game system "%s" is inactive and cannot host new campaigns.', $systemName));
    }
}
