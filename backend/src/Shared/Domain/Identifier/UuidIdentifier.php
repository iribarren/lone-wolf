<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

/**
 * Base for typed UUID identifier value objects (shared kernel).
 *
 * Wrapping raw strings prevents primitive obsession across bounded contexts:
 * a CampaignId is never accidentally passed where an OracleId is expected.
 *
 * @phpstan-consistent-constructor
 */
abstract readonly class UuidIdentifier implements \Stringable
{
    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    protected function __construct(private string $value)
    {
        if (preg_match(self::UUID_V4_PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid identifier for %s.', $value, static::class),
            );
        }
    }

    final public static function fromString(string $value): static
    {
        return new static(strtolower($value));
    }

    final public static function generate(): static
    {
        return new static(self::randomUuidV4());
    }

    final public function toString(): string
    {
        return $this->value;
    }

    final public function equals(self $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }

    #[\Override]
    final public function __toString(): string
    {
        return $this->value;
    }

    private static function randomUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
