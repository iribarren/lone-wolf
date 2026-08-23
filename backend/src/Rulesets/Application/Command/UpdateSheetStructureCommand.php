<?php

declare(strict_types=1);

namespace App\Rulesets\Application\Command;

use App\Rulesets\Domain\FieldDefinition;
use App\Shared\Domain\Identifier\GameSystemId;

final readonly class UpdateSheetStructureCommand
{
    /**
     * @param list<FieldDefinition> $fields
     */
    public function __construct(
        public GameSystemId $systemId,
        public array $fields,
    ) {
    }
}
