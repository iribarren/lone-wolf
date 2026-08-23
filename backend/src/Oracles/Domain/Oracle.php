<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;

/**
 * Aggregate root of the Oracles context: a titled table of weighted textual
 * entries (FR-007) scoped either globally or to exactly one game system
 * (FR-008). Entries MAY rest empty — consulting an empty table is the
 * friendly notice path (FR-011), not an invalid state.
 */
final readonly class Oracle
{
    private const MAX_TITLE_LENGTH = 160;

    /**
     * @param list<OracleEntry> $entries
     */
    private function __construct(
        private OracleId $id,
        private string $title,
        private OracleScope $scope,
        private array $entries,
    ) {
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('Oracle titles must be non-empty.');
        }

        if (mb_strlen($this->title) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Oracle titles must not exceed %d characters.', self::MAX_TITLE_LENGTH));
        }
    }

    /**
     * @param list<OracleEntry> $entries
     */
    public static function start(OracleId $id, string $title, OracleScope $scope, array $entries = []): self
    {
        return new self($id, $title, $scope, array_values($entries));
    }

    /**
     * @param list<OracleEntry> $entries
     * @internal reconstitution only
     */
    public static function reconstitute(OracleId $id, string $title, OracleScope $scope, array $entries): self
    {
        return new self($id, $title, $scope, array_values($entries));
    }

    public function id(): OracleId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function scope(): OracleScope
    {
        return $this->scope;
    }

    /** @return list<OracleEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function entryCount(): int
    {
        return \count($this->entries);
    }

    /**
     * FR-009 at aggregate level: delegates the visibility predicate to the
     * scope strategy.
     */
    public function isAvailableTo(GameSystemId $systemId): bool
    {
        return $this->scope->isAvailableTo($systemId);
    }

    public function withTitle(string $title): self
    {
        return $this->with(title: $title);
    }

    public function withScope(OracleScope $scope): self
    {
        return $this->with(scope: $scope);
    }

    /**
     * Replaces the whole entry set — the reweight/edit authoring path.
     *
     * @param list<OracleEntry> $entries
     */
    public function withEntries(array $entries): self
    {
        return $this->with(entries: $entries);
    }

    public function addEntry(string $text, int $weight): self
    {
        return $this->with(entries: [...$this->entries, OracleEntry::place($text, $weight)]);
    }

    /**
     * @param list<OracleEntry>|null $entries
     */
    private function with(
        ?string $title = null,
        ?OracleScope $scope = null,
        ?array $entries = null,
    ): self {
        return new self(
            $this->id,
            $title ?? $this->title,
            $scope ?? $this->scope,
            $entries ?? $this->entries,
        );
    }}
