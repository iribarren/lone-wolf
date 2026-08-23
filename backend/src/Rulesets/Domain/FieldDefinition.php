<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

/**
 * A single character-sheet field (FR-022/FR-024): typed, with independent
 * PC/NPC requirement flags. `select` fields must declare their options.
 */
final readonly class FieldDefinition
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

    /**
     * @param list<string> $options
     */
    public static function text(string $key, string $label, bool $requiredForPc = false, bool $requiredForNpc = false, array $options = []): self
    {
        if ($options !== []) {
            throw new \InvalidArgumentException(sprintf('Options are allowed on select fields only ("%s").', $key));
        }

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
        if ($options === []) {
            throw new \InvalidArgumentException(sprintf('Select field "%s" requires at least one option.', $key));
        }

        return new self($key, $label, self::TYPE_SELECT, $requiredForPc, $requiredForNpc, array_values($options));
    }

    /**
     * @param array{key:string,label:string,type:string,required_for_pc:bool,required_for_npc:bool,options:list<string>}|array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $key = is_string($payload['key'] ?? null) ? $payload['key'] : '';
        $label = is_string($payload['label'] ?? null) ? $payload['label'] : '';
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $pc = is_bool($payload['required_for_pc'] ?? null) && $payload['required_for_pc'];
        $npc = is_bool($payload['required_for_npc'] ?? null) && $payload['required_for_npc'];
        $options = array_values(array_filter(
            is_array($payload['options'] ?? null) ? $payload['options'] : [],
            static fn (mixed $option): bool => is_string($option),
        ));

        return match ($type) {
            self::TYPE_SELECT => self::select($key, $label, $options, $pc, $npc),
            self::TYPE_NUMBER => self::number($key, $label, $pc, $npc),
            default => self::text($key, $label, $pc, $npc),
        };
    }

    /** @return array{key:string,label:string,type:string,required_for_pc:bool,required_for_npc:bool,options:list<string>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required_for_pc' => $this->requiredForPc,
            'required_for_npc' => $this->requiredForNpc,
            'options' => $this->options,
        ];
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
