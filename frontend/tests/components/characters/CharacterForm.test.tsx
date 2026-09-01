import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import CharacterForm from '@/components/characters/CharacterForm';
import type { SheetFieldView } from '@/components/characters/CharacterPanel';

/** The seeded Scene-Sequel Demo shape: one number field, PCs only. */
const sceneSequel: SheetFieldView[] = [
    { key: 'hp', label: 'Hit points', type: 'number', requiredForPc: true, requiredForNpc: false },
];

/** The seeded Act Ladder shape: a different set with different requirements. */
const actLadder: SheetFieldView[] = [
    { key: 'willpower', label: 'Willpower', type: 'number', requiredForPc: true, requiredForNpc: false },
    { key: 'discipline', label: 'Discipline', type: 'text', requiredForPc: true, requiredForNpc: true },
];

const withSelect: SheetFieldView[] = [
    ...sceneSequel,
    { key: 'class', label: 'Class', type: 'select', requiredForPc: true, requiredForNpc: false, options: ['Fighter', 'Mage'] },
];

describe('CharacterForm', () => {
    it('renders one labelled control per field, typed as the structure says', () => {
        render(<CharacterForm fields={withSelect} onSubmit={vi.fn()} />);

        expect(screen.getByLabelText(/^Name/)).toHaveAttribute('type', 'text');
        // A numeric text input, not type="number": the browser must not eat a
        // wrong-typed entry before the sheet gets to refuse it by name.
        expect(screen.getByLabelText(/Hit points/)).toHaveAttribute('inputmode', 'numeric');

        const klass = screen.getByLabelText(/Class/);
        expect(klass.tagName).toBe('SELECT');
        expect(
            Array.from((klass as HTMLSelectElement).options)
                .map((option) => option.value)
                .filter((value) => value !== ''),
        ).toEqual(['Fighter', 'Mage']);
    });

    it('renders a differently shaped sheet with no code change', () => {
        const { rerender } = render(<CharacterForm fields={sceneSequel} onSubmit={vi.fn()} />);

        expect(screen.getByLabelText(/Hit points/)).toBeInTheDocument();

        rerender(<CharacterForm fields={actLadder} onSubmit={vi.fn()} />);

        expect(screen.getByLabelText(/Willpower/)).toBeInTheDocument();
        expect(screen.getByLabelText(/Discipline/)).toBeInTheDocument();
        expect(screen.queryByLabelText(/Hit points/)).not.toBeInTheDocument();
    });

    it('marks a PC-only field required for a PC and not for an NPC', () => {
        render(<CharacterForm fields={sceneSequel} onSubmit={vi.fn()} />);

        expect(screen.getByLabelText(/Hit points/)).toHaveAttribute('aria-required', 'true');

        fireEvent.change(screen.getByLabelText(/^Kind/), { target: { value: 'npc' } });

        expect(screen.getByLabelText(/Hit points/)).toHaveAttribute('aria-required', 'false');
    });

    it('sends numbers for number fields and omits the blanks', () => {
        const onSubmit = vi.fn();
        render(<CharacterForm fields={actLadder} onSubmit={onSubmit} />);

        fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Vela' } });
        fireEvent.change(screen.getByLabelText(/Willpower/), { target: { value: '12' } });
        fireEvent.click(screen.getByRole('button', { name: /add character/i }));

        expect(onSubmit).toHaveBeenCalledWith({
            kind: 'pc',
            name: 'Vela',
            attributes: { willpower: 12 },
        });
    });

    it('sends a non-numeric entry untouched so the sheet is the one refusing it', () => {
        const onSubmit = vi.fn();
        render(<CharacterForm fields={sceneSequel} onSubmit={onSubmit} />);

        fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Orrin' } });
        fireEvent.change(screen.getByLabelText(/Hit points/), { target: { value: 'twelve' } });
        fireEvent.click(screen.getByRole('button', { name: /add character/i }));

        expect(onSubmit).toHaveBeenCalledWith({
            kind: 'pc',
            name: 'Orrin',
            attributes: { hp: 'twelve' },
        });
    });

    it('shows each violation against its own field', () => {
        render(
            <CharacterForm
                fields={withSelect}
                violations={[
                    { field: 'hp', message: 'Hit points must be a number.' },
                    { field: 'class', message: 'Class must be one of: Fighter, Mage.' },
                ]}
                onSubmit={vi.fn()}
            />,
        );

        const hp = screen.getByTestId('field-error-hp');
        const klass = screen.getByTestId('field-error-class');

        expect(hp).toHaveTextContent('Hit points must be a number.');
        expect(hp).not.toHaveTextContent('Class must be one of');
        expect(klass).toHaveTextContent('Class must be one of: Fighter, Mage.');
        expect(screen.getByLabelText(/Hit points/)).toHaveAttribute('aria-invalid', 'true');
    });

    it('reports a refusal that names no sheet field in its own region', () => {
        render(
            <CharacterForm
                fields={sceneSequel}
                character={{ id: 'c-1', kind: 'pc', name: 'Vex', attributes: { hp: 14 } }}
                violations={[{ field: 'kind', message: "A character's kind cannot change." }]}
                onSubmit={vi.fn()}
            />,
        );

        expect(screen.getByTestId('character-form-error')).toHaveTextContent(
            "A character's kind cannot change.",
        );
    });

    it('disables submitting while pending and keeps what was typed on a refusal', () => {
        const { rerender } = render(<CharacterForm fields={sceneSequel} onSubmit={vi.fn()} />);

        fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Vela' } });
        fireEvent.change(screen.getByLabelText(/Hit points/), { target: { value: '12' } });

        rerender(<CharacterForm fields={sceneSequel} pending onSubmit={vi.fn()} />);
        expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();

        rerender(
            <CharacterForm
                fields={sceneSequel}
                violations={[{ field: 'hp', message: 'Hit points must be a number.' }]}
                onSubmit={vi.fn()}
            />,
        );

        expect(screen.getByLabelText(/^Name/)).toHaveValue('Vela');
        expect(screen.getByLabelText(/Hit points/)).toHaveValue('12');
    });

    it('offers no kind control when editing an existing character', () => {
        render(
            <CharacterForm
                fields={sceneSequel}
                character={{ id: 'c-1', kind: 'npc', name: 'Mira', attributes: { hp: 3 } }}
                onSubmit={vi.fn()}
            />,
        );

        expect(screen.queryByLabelText(/^Kind/)).not.toBeInTheDocument();
        expect(screen.getByTestId('character-form-kind')).toHaveTextContent('NPC');
        expect(screen.getByLabelText(/^Name/)).toHaveValue('Mira');
    });

    it('keeps the kind of the character being edited on save', () => {
        const onSubmit = vi.fn();
        render(
            <CharacterForm
                fields={sceneSequel}
                character={{ id: 'c-1', kind: 'npc', name: 'Mira', attributes: { hp: 3 } }}
                onSubmit={onSubmit}
            />,
        );

        fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Mira the Debtor' } });
        fireEvent.click(screen.getByRole('button', { name: /save character/i }));

        expect(onSubmit).toHaveBeenCalledWith({
            kind: 'npc',
            name: 'Mira the Debtor',
            attributes: { hp: 3 },
        });
    });

    it('explains a system with no sheet structure instead of showing an empty form', () => {
        render(
            <CharacterForm
                fields={[]}
                sheetless
                error="This game system defines no sheet structure yet."
                onSubmit={vi.fn()}
            />,
        );

        expect(screen.getByTestId('character-form-error')).toHaveTextContent(
            'This game system defines no sheet structure yet.',
        );
        expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument();
    });

    it('offers an input for every field the sheet refused when the structure is not known yet', () => {
        // A campaign with no characters carries no structure metadata, so the
        // first save is what reveals the shape (see the component's note).
        const { rerender } = render(<CharacterForm fields={[]} onSubmit={vi.fn()} />);

        expect(screen.getByLabelText(/^Name/)).toBeInTheDocument();
        expect(screen.queryByTestId('character-field-hp')).not.toBeInTheDocument();

        rerender(
            <CharacterForm
                fields={[]}
                violations={[{ field: 'hp', message: 'Hit points is required.' }]}
                onSubmit={vi.fn()}
            />,
        );

        const hp = screen.getByTestId('character-field-hp');
        expect(within(hp).getByRole('alert')).toHaveTextContent('Hit points is required.');
        expect(screen.getByLabelText(/hp/)).toBeInTheDocument();
    });
});
