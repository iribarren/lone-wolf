<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

final readonly class FlowStage
{
    public function __construct(private string $name, private string $guidance = '')
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Stage names must be non-empty.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    /** Pacing guidance shown while the campaign sits on this stage (FR-013/FR-014). */
    public function guidance(): string
    {
        return $this->guidance;
    }

    /**
     * @return array{name: string, guidance: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'guidance' => $this->guidance];
    }

    /**
     * @param array{name?: string, guidance?: string}|string $payload
     */
    public static function fromArray(array|string $payload): self
    {
        if (is_string($payload)) {
            return new self($payload);
        }

        return new self((string) ($payload['name'] ?? ''), (string) ($payload['guidance'] ?? ''));
    }
}
