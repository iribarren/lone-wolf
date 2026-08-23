<?php

declare(strict_types=1);

namespace App\Characters\Application;

/**
 * Unknown and foreign characters collapse into the same refusal —
 * existence is never disclosed (FR-019 parity with campaigns).
 */
final class CharacterNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested character does not exist.');
    }
}
