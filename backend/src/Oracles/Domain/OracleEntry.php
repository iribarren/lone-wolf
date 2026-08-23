<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\OracleEntryId;

/**
 * One weighted textual result row of an oracle table (FR-007): the weight
 * is a strictly positive relative likelihood consulted in proportion
 * (FR-010, SC-004).
 */
final readonly class OracleEntry
{
    private const MAX_TEXT_LENGTH = 500;

    private function __construct(
        private OracleEntryId $id,
        private string $text,
        private int $weight,
    ) {
        if (trim($this->text) === '') {
            throw new \InvalidArgumentException('Oracle entry text must be non-empty.');
        }

        if (mb_strlen($this->text) > self::MAX_TEXT_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Oracle entry text must not exceed %d characters.', self::MAX_TEXT_LENGTH));
        }

        if ($this->weight < 1) {
            throw new \InvalidArgumentException(sprintf('Oracle entry weights must be positive integers (got %d).', $this->weight));
        }
    }

    public static function place(string $text, int $weight): self
    {
        return new self(OracleEntryId::generate(), $text, $weight);
    }

    /** @internal reconstitution only */
    public static function reconstitute(OracleEntryId $id, string $text, int $weight): self
    {
        return new self($id, $text, $weight);
    }

    public function id(): OracleEntryId
    {
        return $this->id;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function weight(): int
    {
        return $this->weight;
    }
}
