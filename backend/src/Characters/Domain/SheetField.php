<?php

declare(strict_types=1);

namespace App\Characters\Domain;

/**
 * Characters-context mirror of one sheet field. The Rulesets-owned
 * definition is translated into this type at the infrastructure boundary
 * (Constitution II — shared storage, never shared models).
 */
final readonly class SheetField
{
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_SELECT = 'select';

    /**
     * @param list<string> $options
     */
    private function __construct(
        private string $key,
        private string $label,
        private string $type,
        private bool $requiredForPc,
        private bool $requiredForNpc,
        private array $options = [],
    ) {
    }

    public static function text(string $key, string $label, bool $requiredForPc = false, bool $requiredForNpc = false): self
    {
        return new self($key, $label, self::TYPE_TEXT, $requiredForPc, $requiredForNpc);
    }

    public static function number(string $key, string $label, bool $requiredForPc = false, bool $requiredForNpc = false): self
    {
        return new self($key, $label, self::TYPE_NUMBER, $requiredForPc, $requiredForNpc);
    }

    /**
     * @param list<string> $options
     */
    public static function select(string $key, string $label, array $options, bool $requiredForPc = false, bool $requiredForNpc = false): self
    {
        return new self($key, $label, self::TYPE_SELECT, $requiredForPc, $requiredForNpc, array_values($options));
    }

    /**
     * Translates the JSONB payload stored on the game_systems row.
     *
     * @param array<array-key, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $key = is_string($payload['key'] ?? null) ? $payload['key'] : '';
        $label = is_string($payload['label'] ?? null) ? $payload['label'] : '';
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : self::TYPE_TEXT;
        $pc = is_bool($payload['required_for_pc'] ?? null) && $payload['required_for_pc'];
        $npc = is_bool($payload['required_for_npc'] ?? null) && $payload['required_for_npc'];
        $options = array_values(array_filter(
            is_array($payload['options'] ?? null) ? $payload['options'] : [],
            static fn (mixed $option): bool => is_string($option),
        ));

        return new self($key, $label, $type, $pc, $npc, $options);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isRequiredForPc(): bool
    {
        return $this->requiredForPc;
    }

    public function isRequiredForNpc(): bool
    {
        return $this->requiredForNpc;
    }

    /** @return list<string> */
    public function options(): array
    {
        return $this->options;
    }
}
