<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\OracleEntryId;

/**
 * Result of a weighted oracle consultation (FR-011).
 *
 * - {selected} when an entry is chosen proportionally to weights
 * - {emptyTable} when the oracle has no entries
 * - {unavailable} when the oracle is not visible to the campaign's system
 */
final readonly class ConsultationOutcome
{
    public const SELECTED = 'selected';
    public const EMPTY_TABLE = 'empty_table';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        public string $type,
        public ?OracleEntry $selected = null,
        public ?string $reason = null,
    ) {
    }

    public static function emptyTable(): self
    {
        return new self(self::EMPTY_TABLE);
    }

    public static function unavailable(string $reason = 'not available'): self
    {
        return new self(self::UNAVAILABLE, reason: $reason);
    }

    public function isSelected(): bool
    {
        return $this->type === self::SELECTED;
    }

    public function isEmptyTable(): bool
    {
        return $this->type === self::EMPTY_TABLE;
    }

    public function isUnavailable(): bool
    {
        return $this->type === self::UNAVAILABLE;
    }

    public function selected(): ?OracleEntry
    {
        return $this->selected;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}