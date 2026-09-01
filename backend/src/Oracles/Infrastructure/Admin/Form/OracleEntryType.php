<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row of the oracle entries editor. Field names match the jsonb payload
 * keys produced by OracleJsonMapper::entriesToPayload().
 *
 * The hints below mirror the OracleEntry aggregate (non-blank text, at most
 * 500 characters, weight >= 1) so a browser catches the obvious mistakes at
 * the field. The domain stays the authority — it refuses the same input again
 * on save (a weight of 0 reaches it and comes back as a flash), and this type
 * deliberately does not re-implement that rule.
 */
final class OracleEntryType extends AbstractType
{
    /** Mirrors OracleEntry::MAX_TEXT_LENGTH. */
    private const MAX_TEXT_LENGTH = 500;

    /** A row that names a result but no weight is one equally likely result. */
    private const DEFAULT_WEIGHT = 1;

    public function getBlockPrefix(): string
    {
        return 'oracle_entry';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Both children are optional at form level so a blank trailing row is
        // dropped below rather than failing validation; what survives the drop
        // is then held to the aggregate's rules.
        $builder->add('text', TextType::class, [
            'label' => 'Result',
            'required' => false,
            'empty_data' => '',
            'attr' => ['maxlength' => self::MAX_TEXT_LENGTH],
        ]);

        $builder->add('weight', IntegerType::class, [
            'label' => 'Weight',
            'required' => false,
            'attr' => ['min' => 1],
        ]);

        // Compound entries never become null on their own, so a fully blank
        // row is mapped to null here — that is what CollectionType's
        // delete_empty needs to drop the row instead of failing validation.
        $builder->addModelTransformer(new CallbackTransformer(
            static function ($data): mixed {
                if (!is_array($data)) {
                    return $data;
                }

                // The stored payload also carries an `id`; the editor renders
                // only what an author writes, so the row round-trips as the
                // same two keys the command carries.
                return [
                    'text' => is_string($data['text'] ?? null) ? $data['text'] : '',
                    'weight' => self::asWeight($data['weight'] ?? null, self::DEFAULT_WEIGHT),
                ];
            },
            static function ($data): ?array {
                if (!is_array($data)) {
                    return null;
                }

                $text = is_string($data['text'] ?? null) ? trim($data['text']) : '';
                $weight = $data['weight'] ?? null;

                if ($text === '' && ($weight === null || $weight === '')) {
                    return null;
                }

                // A blank weight beside a real result means "as likely as the
                // rest"; anything else — 0 included — travels to the domain
                // verbatim so its refusal is the one the author reads.
                return [
                    'text' => $text,
                    'weight' => self::asWeight($weight, self::DEFAULT_WEIGHT),
                ];
            },
        ));
    }

    private static function asWeight(mixed $weight, int $fallback): int
    {
        if (is_int($weight)) {
            return $weight;
        }

        return is_numeric($weight) ? (int) $weight : $fallback;
    }
}
