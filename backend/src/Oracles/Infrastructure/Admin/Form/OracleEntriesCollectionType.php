<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Structured editor for an oracle's weighted result entries (FR-007). The
 * jsonb payload is a plain list, so this type is the collection itself and
 * each row's child names equal the payload keys — no custom data mapper is
 * needed and submissions normalize back to the identical storage shape.
 *
 * Text and weight validity stay in the domain (OracleEntry): this type
 * deliberately lets a zero weight through so the aggregate's own refusal is
 * what the author reads.
 */
final class OracleEntriesCollectionType extends AbstractType
{
    public function getParent(): string
    {
        return CollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'oracle_entries_collection';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
            'label' => 'Result entries',
            'entry_type' => OracleEntryType::class,
            'entry_options' => ['label' => false],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => true,
            'prototype' => true,
            'prototype_name' => '__entry__',
            'error_bubbling' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // CollectionType's ResizeFormListener reads PRE_SET_DATA and
        // PRE_SUBMIT, both of which carry the data before any model
        // transformer has run. A payload that is not a list of rows — a
        // hand-edited jsonb column, a scalar — therefore has to be coerced
        // here, or it reaches that listener raw and throws
        // UnexpectedTypeException instead of rendering an empty editor.
        // The priority keeps this ahead of the resize listener's own 0.
        $coerce = static function (FormEvent $event): void {
            $event->setData(self::rowsOf($event->getData()));
        };

        foreach ([FormEvents::PRE_SET_DATA, FormEvents::PRE_SUBMIT] as $eventName) {
            $builder->addEventListener($eventName, $coerce, 1);
        }

        // Model-level normalization keeps the jsonb contract exact in both
        // directions, and re-indexes submissions to a plain list so deleting
        // a row leaves no gap in the stored array.
        $builder->addModelTransformer(new CallbackTransformer(
            static fn ($data): array => self::rowsOf($data),
            static fn ($data): array => self::rowsOf($data),
        ));
    }

    /**
     * @return list<array<mixed, mixed>>
     */
    private static function rowsOf(mixed $data): array
    {
        return array_values(array_filter(
            is_array($data) ? $data : [],
            static fn ($row): bool => is_array($row),
        ));
    }
}
