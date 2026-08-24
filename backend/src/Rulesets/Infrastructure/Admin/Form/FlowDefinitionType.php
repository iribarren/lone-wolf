<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Structured editor for a game system's campaign flow. Binds the jsonb
 * FlowPayload array directly — child field names equal the payload keys
 * ("stages", "starting_stage", "transitions") — so no custom data mapper is
 * needed and submissions normalize back to the identical storage shape.
 *
 * Structural validation (unique stage names, starting stage membership,
 * known transition targets, FR-005 occupancy) remains in the domain and the
 * UpdateFlowDefinitionHandler; this type deliberately accepts unknown names.
 */
final class FlowDefinitionType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'flow_definition';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
            'label' => false,
        ]);

        // EasyAdmin's ArrayConfigurator injects CollectionType defaults that
        // are meaningless here; declare them so they are swallowed silently.
        $resolver->setDefined(['entry_type', 'entry_options', 'allow_add', 'allow_delete', 'delete_empty']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Model-level normalization keeps the jsonb contract exact in both
        // directions: stored payloads are coerced into the editor shape, and
        // submissions are re-indexed to plain lists (row deletion keeps the
        // original submitted indices) with a guaranteed string starting stage.
        $builder->addModelTransformer(new CallbackTransformer(
            static function ($data): array {
                $data = is_array($data) ? $data : [];

                return [
                    'stages' => is_array($data['stages'] ?? null) ? array_values($data['stages']) : [],
                    'starting_stage' => is_string($data['starting_stage'] ?? null) ? $data['starting_stage'] : '',
                    'transitions' => is_array($data['transitions'] ?? null) ? array_values($data['transitions']) : [],
                    // Preserve any extra payload keys (forward compatibility).
                ] + array_diff_key($data, ['stages' => null, 'starting_stage' => null, 'transitions' => null]);
            },
            static function ($data): ?array {
                if (!is_array($data)) {
                    return null;
                }

                foreach (['stages', 'transitions'] as $listKey) {
                    $rows = is_array($data[$listKey] ?? null) ? $data[$listKey] : [];
                    $data[$listKey] = array_values(array_filter(
                        $rows,
                        static fn ($row): bool => is_array($row),
                    ));
                }

                $data['starting_stage'] = isset($data['starting_stage']) && is_string($data['starting_stage'])
                    ? $data['starting_stage'] : '';

                return $data;
            },
        ));

        $builder->add('stages', CollectionType::class, [
            'label' => 'Stages',
            'entry_type' => FlowStageType::class,
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => true,
            'prototype' => true,
            'prototype_name' => '__stage__',
            'error_bubbling' => false,
        ]);

        $builder->add('starting_stage', StageNameChoiceType::class, [
            'label' => 'Starting stage',
            'attr' => ['class' => 'js-flow-stage-select js-flow-starting-stage'],
        ]);

        $builder->add('transitions', CollectionType::class, [
            'label' => 'Legal transitions',
            'entry_type' => FlowTransitionType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => true,
            'prototype' => true,
            'prototype_name' => '__transition__',
            'error_bubbling' => false,
        ]);
    }
}
