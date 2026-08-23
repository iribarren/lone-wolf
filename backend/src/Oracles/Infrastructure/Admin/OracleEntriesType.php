<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Admin;

use Symfony\Component\Form\AbstractType;

/**
 * Minimal form type for the oracle's weighted-entries JSON textarea.
 * The actual validation and rebuilding is handled by the application handlers.
 */
final class OracleEntriesType extends AbstractType
{
    public function getParent(): string
    {
        return \Symfony\Component\Form\Extension\Core\Type\TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'oracle_entries';
    }
}