<?php

declare(strict_types=1);

namespace App\Tests\Unit\Oracles;

use App\Oracles\Domain\ConsultationOutcome;
use App\Oracles\Domain\OracleEntry;
use App\Oracles\Domain\WeightedOracleSelector;
use App\Shared\Domain\Identifier\OracleEntryId;
use PHPUnit\Framework\TestCase;

final class WeightedOracleSelectorTest extends TestCase
{
    private WeightedOracleSelector $selector;

    protected function setUp(): void
    {
        $this->selector = new WeightedOracleSelector();
    }

    public function testDistributionOverConsultations(): void
    {
        $entries = [
            OracleEntry::place('First option', 1),
            OracleEntry::place('Second option', 1),
            OracleEntry::place('Third option', 1),
        ];

        $counts = [
            'First option' => 0,
            'Second option' => 0,
            'Third option' => 0,
        ];

        foreach (range(1, 3000) as $i) {
            $result = $this->selector->select($entries);
            $counts[$result->selected()->text()]++;
        }

        foreach ($counts as $option => $count) {
            $deviation = abs($count - 1000) / 1000;
            self::assertLessThan(0.05, $deviation, sprintf(
                'Option %s count %d deviates more than 5%% from expected 1000',
                $option,
                $count,
            ));
        }
    }

public function testEmptyTableOutcome(): void
{
    $outcome = $this->selector->select([]);

    self::assertTrue($outcome->isEmptyTable());
}

    public function testDeterministicReproducibility(): void
    {
        $entries = [
            OracleEntry::place('Deterministic result', 5),
            OracleEntry::place('Alternative', 1),
        ];

        $seed = 12345;
        $result1 = $this->selector->select($entries, $seed);
        $result2 = $this->selector->select($entries, $seed);

        self::assertSame($result1->selected()->text(), $result2->selected()->text());
    }

    public function testConsultationOutcomeSelected(): void
    {
        $entry = OracleEntry::place('The answer', 1);
        $outcome = $this->selector->consult([$entry]);

        self::assertTrue($outcome->isSelected());
        self::assertSame('The answer', $outcome->selected()->text());
    }

    public function testConsultationOutcomeEmptyTable(): void
    {
        $outcome = $this->selector->consult([]);

        self::assertTrue($outcome->isEmptyTable());
    }
}