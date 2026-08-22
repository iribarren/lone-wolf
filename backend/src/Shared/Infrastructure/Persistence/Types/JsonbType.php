<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Native PostgreSQL JSONB column type.
 *
 * Value conversion is inherited from Doctrine's JsonType; only the SQL
 * declaration differs so schema tooling emits binary-JSON columns for
 * character attributes, flow definitions and journal snapshots
 * (data-model.md persistence notes).
 */
final class JsonbType extends JsonType
{
    public const NAME = 'jsonb';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }
}
