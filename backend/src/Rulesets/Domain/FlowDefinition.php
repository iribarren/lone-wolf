<?php

declare(strict_types=1);

namespace App\Rulesets\Domain;

/**
 * Immutable campaign-flow definition owned 1:1 by a game system.
 *
 * Invariants (FR-002..FR-004):
 * - at least two named stages,
 * - stage names are unique and non-empty,
 * - exactly one designated starting stage that belongs to the flow,
 * - every transition references existing stages.
 */
final readonly class FlowDefinition
{
    /** @var list<FlowStage> */
    private array $stages;

    /** @var array<string, FlowStage> */
    private array $byName;

    /**
     * @param list<FlowStage>      $stages
     * @param list<FlowTransition> $transitions
     */
    private function __construct(
        array $stages,
        private readonly FlowStage $startingStage,
        private readonly array $transitions,
    ) {
        $byName = [];
        foreach ($stages as $stage) {
            if (isset($byName[$stage->name()])) {
                throw new \InvalidArgumentException(sprintf('Stage names must be unique ("%s" duplicated).', $stage->name()));
            }

            $byName[$stage->name()] = $stage;
        }

        foreach ($transitions as $transition) {
            if (!isset($byName[$transition->from]) || !isset($byName[$transition->to])) {
                throw new \InvalidArgumentException(sprintf(
                    'Transition "%s -> %s" references an unknown stage.',
                    $transition->from,
                    $transition->to,
                ));
            }
        }

        $this->stages = array_values($stages);
        $this->byName = $byName;
    }

    /**
     * @param list<FlowStage>|list<string> $stages
     * @param list<FlowTransition>         $transitions
     */
    public static function create(array $stages, string $startingStage, array $transitions): self
    {
        $objects = array_map(
            static fn (FlowStage|string $stage): FlowStage => $stage instanceof FlowStage ? $stage : FlowStage::fromArray($stage),
            $stages,
        );

        if (\count($objects) < 2) {
            throw new \InvalidArgumentException('A flow needs at least two stages.');
        }

        foreach ($objects as $stage) {
            if ($stage->name() === $startingStage) {
                return new self($objects, $stage, $transitions);
            }
        }

        throw new \InvalidArgumentException(sprintf('Starting stage "%s" is not part of the flow.', $startingStage));
    }

    /** @return list<FlowStage> */
    public function stages(): array
    {
        return $this->stages;
    }

    public function startingStage(): FlowStage
    {
        return $this->startingStage;
    }

    /** @return list<FlowTransition> */
    public function transitions(): array
    {
        return $this->transitions;
    }

    public function hasStage(string $name): bool
    {
        return isset($this->byName[$name]);
    }

    /** @return list<string> */
    public function legalNextStages(string $from): array
    {
        $next = [];
        foreach ($this->transitions as $transition) {
            if ($transition->from === $from) {
                $next[$transition->to] = true;
            }
        }

        return array_keys($next);
    }
}
