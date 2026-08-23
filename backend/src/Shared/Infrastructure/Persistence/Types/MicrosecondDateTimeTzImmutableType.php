<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;

/**
 * datetimetz_immutable with sub-second precision preserved.
 *
 * DBAL's PostgreSQL platform binds timestamps as 'Y-m-d H:i:sO', silently
 * truncating microseconds — which collapses same-second journal writes into
 * an unordered tie (SC-008 newest-first reads). This override keeps the
 * fractional part on the way in; reads inherit the parent conversion.
 */
final class MicrosecondDateTimeTzImmutableType extends DateTimeTzImmutableType
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        \assert($value instanceof \DateTimeImmutable);

        return $value->format('Y-m-d H:i:s.uP');
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            return parent::convertToPHPValue($value, $platform);
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.uP', $value);

        if ($parsed === false) {
            // Legacy whole-second rows written before this type existed.
            return parent::convertToPHPValue($value, $platform);
        }

        return $parsed;
    }
}
