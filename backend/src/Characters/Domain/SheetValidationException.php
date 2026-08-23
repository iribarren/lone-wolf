<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * Raised when a write fails sheet conformity (FR-023). The API boundary
 * translates it into a 422 SheetValidationProblem carrying the violations.
 */
final class SheetValidationException extends \DomainException
{
    /**
     * @param list<AttributeViolation> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct('The character attributes do not conform to the system\'s sheet structure.');
    }

    /** @return list<AttributeViolation> */
    public function violations(): array
    {
        return $this->violations;
    }
}
