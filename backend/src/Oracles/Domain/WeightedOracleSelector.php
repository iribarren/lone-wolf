<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Selects one entry from an oracle table proportionally to the entries'
 * strictly positive weights (FR-010), verified statistically over seeded
 * runs (SC-004). A seed makes any single consultation reproducible.
 */
final class WeightedOracleSelector
{
    /** @var list<OracleEntry> */
    private array $entries;

    /**
     * @param list<OracleEntry> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    /**
     * @param list<OracleEntry> $entries
     */
    public function withEntries(array $entries): self
    {
        return new self($entries);
    }

    /**
     * Cumulative-weight pick: a uniform draw over [1..totalWeight] falls in
     * exactly one entry's weight span, so P(entry) = weight / total.
     *
     * @param list<OracleEntry>|null $entries optional override; defaults to the configured set
     * @param int|null               $seed    fixed seed for reproducible consultations
     */
    public function select(?array $entries = null, ?int $seed = null): ConsultationOutcome
    {
        $entries ??= $this->entries;

        // An empty table is the friendly notice path, not a failure (FR-011).
        if (\count($entries) === 0) {
            return ConsultationOutcome::emptyTable();
        }

        $cumulativeWeights = [];
        $runningTotal = 0;

        foreach ($entries as $entry) {
            $runningTotal += $entry->weight();
            $cumulativeWeights[] = $runningTotal;
        }

        $randomizer = new Randomizer($seed === null ? null : new Mt19937($seed));
        $randomValue = $randomizer->getInt(1, $runningTotal);

        $selectedIndex = 0;
        foreach ($cumulativeWeights as $index => $cumulativeWeight) {
            if ($randomValue <= $cumulativeWeight) {
                $selectedIndex = $index;
                break;
            }
        }

        return ConsultationOutcome::forSelection($entries[$selectedIndex]);
    }

    /**
     * Alias of select() for consultation-style call sites.
     *
     * @param list<OracleEntry>|null $entries optional override
     * @param int|null               $seed    fixed seed for reproducible consultations
     */
    public function consult(?array $entries = null, ?int $seed = null): ConsultationOutcome
    {
        return $this->select($entries, $seed);
    }

    /** @return list<OracleEntry> */
    public function entries(): array
    {
        return $this->entries;
    }
}
