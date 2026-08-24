'use client';

/**
 * Floating dice roller during play (T092, US6 — FR-026..029).
 * Pure presentational so states are directly testable (T093): strict
 * NdM±K input, every die shown next to the modified total, a typed
 * refusal notice instead of any result, and a log-to-journal action.
 */
export interface DiceRollResultView {
    notation: string;
    diceValues: number[];
    modifier: number;
    total: number;
}

export type DiceProblemReason = 'malformed' | 'invalid_count' | 'invalid_faces' | 'out_of_bounds';

export interface DiceProblemView {
    reason: DiceProblemReason;
    detail?: string | null;
}

export interface DiceRollerWidgetProps {
    open: boolean;
    rolling?: boolean;
    logging?: boolean;
    logged?: boolean;
    result?: DiceRollResultView | null;
    problem?: DiceProblemView | null;
    onClose(): void;
    onRoll(notation: string): void;
    onLogResult(): void;
}

/** Specific, helpful guidance per refusal reason (FR-027). */
const REASON_MESSAGES: Record<DiceProblemReason, string> = {
    malformed: 'That is not standard NdM±K notation — try something like 1d20+5.',
    invalid_count: 'The die count must be at least 1.',
    invalid_faces: 'A die needs at least 2 faces.',
    out_of_bounds: 'Those numbers are outside the supported range (N ≤ 50, M ≤ 1000, |K| ≤ 10000).',
};

export default function DiceRollerWidget({
    open,
    rolling = false,
    logging = false,
    logged = false,
    result = null,
    problem = null,
    onClose,
    onRoll,
    onLogResult,
}: DiceRollerWidgetProps) {
    if (!open) {
        return (
            <section aria-label="Dice roller" data-testid="dice-widget-closed">
                <p>Dice roller closed.</p>
            </section>
        );
    }

    return (
        <aside
            aria-label="Dice roller"
            style={{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem' }}
            data-testid="dice-widget"
        >
            <header style={{ display: 'flex', justifyContent: 'space-between' }}>
                <h2>Dice</h2>
                <button type="button" onClick={onClose}>Close</button>
            </header>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    const field = new FormData(event.currentTarget);
                    onRoll(String(field.get('notation') ?? '').trim());
                }}
            >
                <label htmlFor="dice-notation">Dice notation</label>
                <input id="dice-notation" name="notation" placeholder="e.g. 1d20+5" />

                {' '}
                <button type="submit" disabled={rolling}>
                    {rolling ? 'Rolling…' : 'Roll'}
                </button>
            </form>

            {problem ? (
                <p role="alert" data-testid="dice-error">
                    {REASON_MESSAGES[problem.reason]}
                </p>
            ) : null}

            {!problem && result ? (
                <section
                    aria-label={`Result of ${result.notation}`}
                    data-testid="dice-result"
                    style={{ marginTop: '1rem', borderTop: '1px solid #eee', paddingTop: '0.5rem' }}
                >
                    <p>
                        <strong>{result.notation}</strong>
                    </p>

                    <ul data-testid="dice-chips" style={{ listStyle: 'none', display: 'flex', gap: '0.25rem', padding: 0 }}>
                        {result.diceValues.map((value, index) => (
                            <li key={index} data-testid="dice-chip" style={{ border: '1px solid #999', borderRadius: 4, padding: '0 0.4rem' }}>
                                {value}
                            </li>
                        ))}
                    </ul>

                    <p>
                        Total:{' '}
                        <strong data-testid="dice-total">
                            {result.total}
                        </strong>
                        {result.modifier !== 0 ? (
                            <span data-testid="dice-modifier"> ({result.modifier > 0 ? '+' : ''}{result.modifier})</span>
                        ) : null}
                    </p>

                    {logged ? (
                        <p role="status" data-testid="dice-logged">Logged to your journal.</p>
                    ) : (
                        <button type="button" disabled={logging} onClick={onLogResult}>
                            {logging ? 'Logging…' : 'Log to journal'}
                        </button>
                    )}
                </section>
            ) : null}
        </aside>
    );
}
