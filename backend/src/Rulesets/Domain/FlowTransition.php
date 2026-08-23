<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

final readonly class FlowTransition
{
    public function __construct(
        public string $from,
        public string $to,
    ) {
    }

    public static function fromNames(string $from, string $to): self
    {
        return new self($from, $to);
    }

    /** @return array{from: string, to: string} */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }

    /** @param array{from: string, to: string} $payload */
    public static function fromArray(array $payload): self
    {
        return new self((string) ($payload['from'] ?? ''), (string) ($payload['to'] ?? ''));
    }
}
