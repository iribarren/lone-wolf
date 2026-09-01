import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import OracleDrawer, {
    type ConsultationOutcomeView,
    type OracleSummaryView,
} from '@/components/oracles/OracleDrawer';

const tables: OracleSummaryView[] = [
    { oracleId: 'o-global', title: 'Weather', scopeType: 'global', entryCount: 4 },
    { oracleId: 'o-scoped', title: 'Encounters', scopeType: 'system', entryCount: 2 },
];

const selectedOutcome: ConsultationOutcomeView = {
    status: 'selected',
    entry: { entryId: 'e-1', text: 'Cold rain sets in.' },
};

describe('OracleDrawer', () => {
    it('renders only the scoped table list handed to it, with scope labels', () => {
        render(
            <OracleDrawer
                open
                oracles={[tables[0]]}
                onClose={vi.fn()}
                onConsult={vi.fn()}
                onSave={vi.fn()}
            />,
        );

        const list = screen.getByTestId('oracles-list');
        expect(list).toHaveTextContent('Weather');
        expect(list).toHaveTextContent('global');
        expect(list).not.toHaveTextContent('Encounters');
    });

    it('renders a selected result with its oracle title', () => {
        render(
            <OracleDrawer
                open
                oracles={tables}
                consultedTitle="Weather"
                outcome={selectedOutcome}
                onClose={vi.fn()}
                onConsult={vi.fn()}
                onSave={vi.fn()}
            />,
        );

        expect(screen.getByTestId('oracle-result')).toHaveTextContent('Cold rain sets in.');
    });

    it('shows the friendly empty-table notice instead of an error', () => {
        render(
            <OracleDrawer
                open
                oracles={tables}
                outcome={{ status: 'empty_table' }}
                onClose={vi.fn()}
                onConsult={vi.fn()}
                onSave={vi.fn()}
            />,
        );

        expect(screen.getByTestId('oracle-empty-table')).toHaveTextContent(
            'This table is empty',
        );
        expect(screen.queryByTestId('oracle-result')).not.toBeInTheDocument();
    });

    it('consults the chosen table and saves the shown result with interpretation', () => {
        const onConsult = vi.fn();
        const onSave = vi.fn();
        render(
            <OracleDrawer
                open
                oracles={tables}
                consultedTitle="Weather"
                outcome={selectedOutcome}
                onClose={vi.fn()}
                onConsult={onConsult}
                onSave={onSave}
            />,
        );

        const weatherRow = screen.getByText('Weather').closest('li');
        expect(weatherRow).not.toBeNull();

        if (weatherRow) {
            fireEvent.click(within(weatherRow).getByRole('button', { name: /consult/i }));
        }

        expect(onConsult).toHaveBeenCalledWith('o-global');

        fireEvent.change(screen.getByLabelText(/interpretation/i), {
            target: { value: 'An omen of the crossing ahead.' },
        });
        fireEvent.click(screen.getByRole('button', { name: /save to journal/i }));

        expect(onSave).toHaveBeenCalledTimes(1);
        expect(onSave).toHaveBeenCalledWith('Cold rain sets in.', 'An omen of the crossing ahead.');
    });

    it('renders nothing while closed', () => {
        render(
            <OracleDrawer
                open={false}
                oracles={tables}
                onClose={vi.fn()}
                onConsult={vi.fn()}
                onSave={vi.fn()}
            />,
        );

        expect(screen.queryByTestId('oracles-drawer-closed')).not.toBeInTheDocument();
        expect(screen.queryByText(/closed\./i)).not.toBeInTheDocument();
        expect(screen.queryByTestId('oracles-drawer')).not.toBeInTheDocument();
        expect(screen.queryByTestId('oracles-list')).not.toBeInTheDocument();
    });
});
