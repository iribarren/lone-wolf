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

    /** @param array{key:string,label:string,type:string,required_for_pc:bool,required_for_npc:bool,options:list<string>} $payload */
    public static function fromArray(array $payload): self
    {
        return match ((string) ($payload['type'] ?? '')) {
            self::TYPE_SELECT => self::select(
                (string) $payload['key'],
                (string) $payload['label'],
                (array) ($payload['options'] ?? []),
                (bool) ($payload['required_for_pc'] ?? false),
                (bool) ($payload['required_for_npc'] ?? false),
            ),
            self::TYPE_NUMBER => self::number(
                (string) $payload['key'],
                (string) $payload['label'],
                (bool) ($payload['required_for_pc'] ?? false),
                (bool) ($payload['required_for_npc'] ?? false),
            ),
            default => self::text(
                (string) $payload['key'],
                (string) $payload['label'],
                (bool) ($payload['required_for_pc'] ?? false),
                (bool) ($payload['required_for_npc'] ?? false),
            ),
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
