<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

final readonly class FlowStage
{
    public function __construct(private string $name)
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Stage names must be non-empty.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }
}
