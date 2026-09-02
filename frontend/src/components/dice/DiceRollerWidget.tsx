'use client';

import styles from './DiceRollerWidget.module.css';

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

export type DiceProblemReason =
    | 'malformed'
    | 'invalid_count'
    | 'invalid_faces'
    | 'out_of_bounds'
    | 'unreadable_result';

/**
 * The result shape is what the widget renders, so it is also what callers
 * must prove before handing anything over: an endpoint answering off-contract
 * once blanked the whole page (audit A5). Nothing is assumed about `value`.
 */
export function isDiceRollResultView(value: unknown): value is DiceRollResultView {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Partial<Record<keyof DiceRollResultView, unknown>>;

    return typeof candidate.notation === 'string'
        && Array.isArray(candidate.diceValues)
        && candidate.diceValues.every((die) => typeof die === 'number')
        && typeof candidate.modifier === 'number'
        && typeof candidate.total === 'number';
}

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
    unreadable_result: 'The roll came back in a shape this app cannot read — reload to see whether it reached your journal.',
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
        return null;
    }

    // A result the widget cannot read is no result at all — never a render
    // that takes the page down with it (audit A5).
    const shown = isDiceRollResultView(result) ? result : null;

    return (
        <aside
            aria-label="Dice roller"
            className={styles.card}
            data-testid="dice-widget"
        >
            <header className={styles.head}>
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

            {!problem && shown ? (
                <section
                    aria-label={`Result of ${shown.notation}`}
                    data-testid="dice-result"
                    className={styles.result}
                >
                    <p>
                        <strong>{shown.notation}</strong>
                    </p>

                    <ul data-testid="dice-chips" className={styles.faces}>
                        {shown.diceValues.map((value, index) => (
                            <li key={index} data-testid="dice-chip" className={styles.face}>
                                {value}
                            </li>
                        ))}
                    </ul>

                    <p>
                        Total:{' '}
                        <strong data-testid="dice-total">
                            {shown.total}
                        </strong>
                        {shown.modifier !== 0 ? (
                            <span data-testid="dice-modifier"> ({shown.modifier > 0 ? '+' : ''}{shown.modifier})</span>
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
