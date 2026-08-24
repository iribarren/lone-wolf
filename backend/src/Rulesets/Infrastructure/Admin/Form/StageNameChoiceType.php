<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A stage-name select whose accepted values are not fixed at build time —
 * options are populated client-side from the stage rows (flow-editor.js) and
 * every submitted value is accepted server-side by LenientStageNameLoader.
 */
final class StageNameChoiceType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'flow_stage_name_choice';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_loader' => new LenientStageNameLoader(),
            'required' => false,
            'placeholder' => '',
            'attr' => ['class' => 'js-flow-stage-select'],
        ]);
    }
}
