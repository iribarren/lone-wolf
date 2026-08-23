<?php

declare(strict_types=1);

namespace App\Characters\Application;

/**
 * The owning system defines no sheet structure, so there is nothing for a
 * character to conform to (US5 precondition — structures are authored in
 * the backoffice first).
 */
final class SystemHasNoSheetException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This game system defines no sheet structure yet.');
    }
}
