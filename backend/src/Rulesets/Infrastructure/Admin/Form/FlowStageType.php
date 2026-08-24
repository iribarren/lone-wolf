<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row of the flow editor's stage collection. Field names match the jsonb
 * payload keys produced by FlowStage::toArray().
 */
final class FlowStageType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'flow_stage';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Stage name',
            'attr' => ['class' => 'js-flow-stage-name'],
        ]);

        $builder->add('guidance', TextareaType::class, [
            'label' => 'Guidance',
            'required' => false,
            'empty_data' => '',
        ]);

        // Compound entries never become null on their own, so a fully blank
        // row is mapped to null here — that is what CollectionType's
        // delete_empty needs to drop the row instead of failing validation.
        $builder->addModelTransformer(new CallbackTransformer(
            static fn ($data): mixed => $data,
            static function ($data): ?array {
                if (!is_array($data)) {
                    return null;
                }

                $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
                $guidance = isset($data['guidance']) && is_string($data['guidance']) ? trim($data['guidance']) : '';

                return ($name === '' && $guidance === '') ? null : ['name' => $name, 'guidance' => $guidance];
            },
        ));
    }
}
