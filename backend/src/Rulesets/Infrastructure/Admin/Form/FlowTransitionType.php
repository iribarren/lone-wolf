<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row of the flow editor's transition collection. Field names match the
 * jsonb payload keys produced by FlowTransition::toArray().
 */
final class FlowTransitionType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'flow_transition';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
            'label' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('from', StageNameChoiceType::class, [
            'label' => 'From stage',
        ]);

        $builder->add('to', StageNameChoiceType::class, [
            'label' => 'To stage',
        ]);

        // Blank rows (added but never filled) are dropped by the collection's
        // delete_empty — see FlowStageType for the same trick.
        $builder->addModelTransformer(new CallbackTransformer(
            static fn ($data): mixed => $data,
            static function ($data): ?array {
                if (!is_array($data)) {
                    return null;
                }

                $from = isset($data['from']) && is_string($data['from']) ? trim($data['from']) : '';
                $to = isset($data['to']) && is_string($data['to']) ? trim($data['to']) : '';

                return ($from === '' && $to === '') ? null : ['from' => $from, 'to' => $to];
            },
        ));
    }
}
