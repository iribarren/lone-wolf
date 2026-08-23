<?php

declare(strict_types=1);

namespace App\Oracles\Domain;

use App\Oracles\Domain\ConsultationOutcome;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Selects a weighted-random entry from an oracle table (FR-010, SC-004).
 *
 * Uses cumulative-weight selection via an injected RandomSource for
 * deterministic reproducibility and testability. Entry weights must be
 * strictly positive (enforced by OracleEntry).
 */
final class WeightedOracleSelector
{
    /** @var list<OracleEntry> */
    private array $entries;

    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    public function withEntries(array $entries): self
    {
        return new self($entries);
    }

    /**
     * Select one entry by cumulative-weight algorithm.
     *
     * @param list<OracleEntry>|null $entries Optional entries override; defaults to those set on the selector
     * @param int|null $seed Optional seed for deterministic reproducibility
     * @return ConsultationOutcome
     */
    public function select(?array $entries = null, ?int $seed = null): ConsultationOutcome
    {
        $entries = $entries ?? $this->entries;

        if (\count($entries) === 0) {
            return ConsultationOutcome::emptyTable();
        }

        $cumulativeWeights = [];
        $runningTotal = 0;

        foreach ($entries as $entry) {
            $runningTotal += $entry->weight();
            $cumulativeWeights[] = $runningTotal;
        }

        $totalWeight = $runningTotal;
        $randomizer = new Randomizer();

        if (null !== $seed) {
            $engine = new Mt19937((int) $seed);
            $randomizer = new Randomizer($engine);
        }

        $randomValue = $randomizer->getInt(1, $totalWeight);

        $selectedIndex = 0;
        foreach ($cumulativeWeights as $index => $cumulativeWeight) {
            if ($randomValue <= $cumulativeWeight) {
                $selectedIndex = $index;
                break;
            }
        }

        $selected = $entries[$selectedIndex];

        return new ConsultationOutcome(ConsultationOutcome::SELECTED, $selected);
    }

    /**
     * Alias for select() for consultation-style API.
     *
     * @param list<OracleEntry>|null $entries Optional entries override
     * @param int|null $seed Optional seed for deterministic reproducibility
     * @return ConsultationOutcome
     */
    public function consult(?array $entries = null, ?int $seed = null): ConsultationOutcome
    {
        return $this->select($entries, $seed);
    }

    /**
     * Get entries currently configured on the selector.
     *
     * @return list<OracleEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }
}