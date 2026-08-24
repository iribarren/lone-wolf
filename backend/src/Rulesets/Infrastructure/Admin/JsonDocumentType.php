<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\SheetStructure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * JSON document textarea bound to a jsonb array column, validated against
 * the matching domain value object on submit.
 */
final class JsonDocumentType extends AbstractType
{
    public const OPTION_IS_SHEET = 'is_sheet_structure';

    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION_IS_SHEET, false);
        $resolver->setAllowedTypes(self::OPTION_IS_SHEET, 'bool');

        // EasyAdmin's ArrayConfigurator injects CollectionType defaults that
        // are meaningless for this plain textarea; declare them so they are
        // swallowed silently.
        $resolver->setDefined(['entry_type', 'entry_options', 'allow_add', 'allow_delete', 'delete_empty']);
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'json_document';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new JsonDocumentTransformer((bool) $options[self::OPTION_IS_SHEET]));
    }
}
