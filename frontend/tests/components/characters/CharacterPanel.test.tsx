import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import CharacterPanel, { type CharacterPanelCharacter } from '@/components/characters/CharacterPanel';

const fighter: CharacterPanelCharacter = {
    id: 'c-1',
    kind: 'pc',
    name: 'Vex',
    attributes: { hp: 14, class: 'Fighter', bond: 'Sworn to the wolf.' },
    reviewStatus: 'clean',
    driftIssues: [],
    structureFields: [
        { key: 'hp', label: 'Hit points', type: 'number', requiredForPc: true, requiredForNpc: false },
        { key: 'class', label: 'Class', type: 'select', requiredForPc: true, requiredForNpc: false, options: ['Fighter', 'Mage'] },
        { key: 'bond', label: 'Bond', type: 'text', requiredForPc: false, requiredForNpc: true },
    ],
};

describe('CharacterPanel', () => {
    it('renders each sheet purely from the returned structure metadata', () => {
        render(<CharacterPanel characters={[fighter]} />);

        expect(screen.getByTestId('character-Vex')).toBeInTheDocument();
        expect(screen.getByText('Hit points')).toBeInTheDocument();
        expect(screen.getByText('Class')).toBeInTheDocument();
        expect(screen.getByText('Bond')).toBeInTheDocument();
        expect(screen.getByText('14')).toBeInTheDocument();
        expect(screen.getByText('Fighter of Fighter/Mage')).toBeInTheDocument();
    });

    it('renders differently shaped sheets without any hardcoded field list', () => {
        const npcOnlyShape: CharacterPanelCharacter = {
            ...fighter,
            id: 'c-2',
            name: 'Mira',
            kind: 'npc',
            attributes: { bond: 'Owes a debt.' },
            structureFields: [
                { key: 'bond', label: 'Bond', type: 'text', requiredForPc: false, requiredForNpc: true },
            ],
        };

        render(<CharacterPanel characters={[fighter, npcOnlyShape]} />);

        const mira = screen.getByTestId('character-Mira');
        expect(mira).toBeInTheDocument();
        expect(within(mira).getByText('Owes a debt.')).toBeInTheDocument();
        // The second sheet has no hp field — nothing renders for it.
        expect(within(mira).queryByText('Hit points')).not.toBeInTheDocument();
    })

    it('shows field-level violations inline', () => {
        render(
            <CharacterPanel
                characters={[fighter]}
                violations={[
                    { field: 'hp', message: 'Hit points must be a number.' },
                    { field: 'class', message: 'Class must be one of: Fighter, Mage.' },
                ]}
            />,
        );

        const violations = screen.getByTestId('sheet-violations');
        expect(violations).toHaveTextContent('hp: Hit points must be a number.');
        expect(violations).toHaveTextContent('class: Class must be one of: Fighter, Mage.');
    });

    it('offers an edit control per character only when the parent handles one', () => {
        render(<CharacterPanel characters={[fighter]} />);
        expect(screen.queryByRole('button', { name: /edit vex/i })).not.toBeInTheDocument();

        const onEdit = vi.fn();
        render(<CharacterPanel characters={[fighter]} onEdit={onEdit} />);
        fireEvent.click(screen.getByRole('button', { name: /edit vex/i }));

        expect(onEdit).toHaveBeenCalledWith(fighter);
    });

    it('flags drifted characters with their issues', () => {
        const drifted: CharacterPanelCharacter = {
            ...fighter,
            reviewStatus: 'flagged_for_review',
            driftIssues: ['hp: Hit points is required.'],
        };

        render(<CharacterPanel characters={[drifted]} />);

        expect(screen.getByTestId('drift-badge-Vex')).toBeInTheDocument();
        expect(screen.getByTestId('drift-issues-Vex')).toHaveTextContent('hp: Hit points is required.');
    });
});
