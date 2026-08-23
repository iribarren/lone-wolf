<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * JSONB-backed attribute payload of a character sheet, keyed by the owning
 * system's FieldDefinition keys. Values stay schemaless here — conformity
 * is judged by {@see AttributeValidator} against a {@see SheetSchema}.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
final readonly class AttributesMap implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        foreach ($values as $key => $_) {
            if (!is_string($key) || trim($key) === '') {
                throw new \InvalidArgumentException('Attribute keys must be non-empty strings.');
            }
        }

        return new self($values);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @return \ArrayIterator<string, mixed>
     */
    #[\Override]
    public function getIterator(): \ArrayIterator
    {
        $iterator = new \ArrayIterator($this->values);

        return $iterator;
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->values);
    }
}
