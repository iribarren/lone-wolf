<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

enum GameSystemStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
