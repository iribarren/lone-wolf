<?php

declare(strict_types=1);

namespace App\Journal\Domain;

/**
 * The three record kinds of the play journal (FR-015/FR-029 and the oracle
 * loop): free narrative, saved oracle consultations, logged dice rolls.
 */
enum JournalEntryKind: string
{
    case Narrative = 'narrative';
    case OracleResult = 'oracle_result';
    case DiceRoll = 'dice_roll';
}
