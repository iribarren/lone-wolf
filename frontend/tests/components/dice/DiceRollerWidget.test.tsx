import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import DiceRollerWidget, {
    type DiceProblemView,
    type DiceRollResultView,
} from '@/components/dice/DiceRollerWidget';

const result: DiceRollResultView = {
    notation: '1d20+5',
    diceValues: [14],
    modifier: 5,
    total: 19,
};

const twoDice: DiceRollResultView = {
    notation: '2d6',
    diceValues: [3, 5],
    modifier: 0,
    total: 8,
};

describe('DiceRollerWidget', () => {
    it('rolls the entered notation', () => {
        const onRoll = vi.fn();
        render(
            <DiceRollerWidget
                open
                onClose={vi.fn()}
                onRoll={onRoll}
                onLogResult={vi.fn()}
            />,
        );

        fireEvent.change(screen.getByLabelText(/dice notation/i), {
            target: { value: '1d20+5' },
        });
        fireEvent.click(screen.getByRole('button', { name: /roll/i }));

        expect(onRoll).toHaveBeenCalledWith('1d20+5');
    });

    it('shows every die as a chip next to the modified total', () => {
        render(
            <DiceRollerWidget
                open
                result={twoDice}
                onClose={vi.fn()}
                onRoll={vi.fn()}
                onLogResult={vi.fn()}
            />,
        );

        const chips = screen.getAllByTestId('dice-chip');
        expect(chips).toHaveLength(2);
        expect(chips[0]).toHaveTextContent('3');
        expect(chips[1]).toHaveTextContent('5');
        expect(screen.getByTestId('dice-total')).toHaveTextContent('8');
    });

    it('displays the modifier next to the total when present', () => {
        render(
            <DiceRollerWidget
                open
                result={result}
                onClose={vi.fn()}
                onRoll={vi.fn()}
                onLogResult={vi.fn()}
            />,
        );

        expect(screen.getByTestId('dice-total')).toHaveTextContent('19');
        expect(screen.getByTestId('dice-modifier')).toHaveTextContent('(+5)');
    });

    it.each([
        ['malformed', 'not standard'],
        ['invalid_count', 'at least 1'],
        ['invalid_faces', 'at least 2 faces'],
        ['out_of_bounds', 'outside the supported range'],
    ] as const)('refuses %s with a specific notice and never shows a result', (reason, fragment) => {
        const problem: DiceProblemView = { reason };
        render(
            <DiceRollerWidget
                open
                result={result}
                problem={problem}
                onClose={vi.fn()}
                onRoll={vi.fn()}
                onLogResult={vi.fn()}
            />,
        );

        expect(screen.getByTestId('dice-error')).toHaveTextContent(fragment);
        // A refused roll must never surface a result — not even a stale one.
        expect(screen.queryByTestId('dice-result')).not.toBeInTheDocument();
        expect(screen.queryByTestId('dice-total')).not.toBeInTheDocument();
    });

    it('logs the shown roll to the journal and confirms', () => {
        const onLogResult = vi.fn();
        const view = render(
            <DiceRollerWidget
                open
                result={result}
                onClose={vi.fn()}
                onRoll={vi.fn()}
                onLogResult={onLogResult}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: /log to journal/i }));
        expect(onLogResult).toHaveBeenCalledTimes(1);

        view.rerender(
            <DiceRollerWidget
                open
                logged
                result={result}
                onClose={vi.fn()}
                onRoll={vi.fn()}
                onLogResult={onLogResult}
            />,
        );

        expect(screen.getByTestId('dice-logged')).toHaveTextContent('Logged to your journal.');
    });

    // A5: the logged-roll endpoint once answered an IRI string where the
    // contract promises an object, and rendering it blanked the whole page.
    // Same guarantee as the refusal case above — a result the widget cannot
    // read is no result at all.
    it.each([
        ['an IRI string', '/api/.well-known/genid/b3984bd9e95e94a4c185'],
        ['an object with no dice values', { notation: '2d6+3', modifier: 3, total: 8 }],
        ['dice values that are not a list', { notation: '2d6+3', diceValues: 4, modifier: 3, total: 8 }],
    ])('never crashes when handed %s as a result', (_label, malformed) => {
        expect(() =>
            render(
                <DiceRollerWidget
                    open
                    result={malformed as unknown as DiceRollResultView}
                    onClose={vi.fn()}
                    onRoll={vi.fn()}
                    onLogResult={vi.fn()}
                />,
            ),
        ).not.toThrow();

        expect(screen.queryByTestId('dice-result')).not.toBeInTheDocument();
        expect(screen.queryByTestId('dice-chip')).not.toBeInTheDocument();
    });

    it('renders nothing while closed', () => {
        render(
            <DiceRollerWidget
                open={false}
                onClose={vi.fn()}
                onRoll={vi.fn()}
                onLogResult={vi.fn()}
            />,
        );

        expect(screen.queryByTestId('dice-widget-closed')).not.toBeInTheDocument();
        expect(screen.queryByText(/closed\./i)).not.toBeInTheDocument();
        expect(screen.queryByTestId('dice-widget')).not.toBeInTheDocument();
        expect(screen.queryByTestId('dice-result')).not.toBeInTheDocument();
    });
});
