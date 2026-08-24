<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin\Form;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Accepts any submitted stage name as a valid choice value. Structural flow
 * validation (unique names, known starting stage, known transition targets)
 * stays with the domain — see FlowDefinition and UpdateFlowDefinitionHandler —
 * so the select never dead-ends an edit with a generic "invalid choice" error.
 */
final class LenientStageNameLoader implements ChoiceLoaderInterface
{
    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        return new ArrayChoiceList([], $value);
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return array<int|string, mixed>
     */
    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        return $values;
    }

    /**
     * @param array<int|string, mixed> $choices
     *
     * @return array<int, string>
     */
    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        $values = array_map(static fn ($choice): string => is_string($choice) ? $choice : '', $choices);

        return array_values($values);
    }
}
