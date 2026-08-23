'use client';

/**
 * Floating oracle drawer during play (T071, US4 — FR-009/010/011).
 * Pure presentational so states are directly testable (T072): scoped
 * listing, one weighted result per consult, friendly empty-table notice,
 * and a save-with-interpretation composer for the shown result.
 */
export interface OracleSummaryView {
    oracleId: string;
    title: string;
    scopeType: string;
    entryCount: number;
}

export interface ConsultedEntryView {
    entryId: string;
    text: string;
}

export interface ConsultationOutcomeView {
    status: 'selected' | 'empty_table' | 'unavailable';
    entry?: ConsultedEntryView | null;
}

export interface OracleDrawerProps {
    open: boolean;
    oracles: OracleSummaryView[];
    loading?: boolean;
    consultingOracleId?: string | null;
    consultedTitle?: string | null;
    outcome?: ConsultationOutcomeView | null;
    saving?: boolean;
    saved?: boolean;
    onClose(): void;
    onConsult(oracleId: string): void;
    onSave(text: string, interpretation: string): void;
}

export default function OracleDrawer({
    open,
    oracles,
    loading = false,
    consultingOracleId = null,
    consultedTitle = null,
    outcome = null,
    saving = false,
    saved = false,
    onClose,
    onConsult,
    onSave,
}: OracleDrawerProps) {
    if (!open) {
        return (
            <section aria-label="Oracles" data-testid="oracles-drawer-closed">
                <p>Oracles drawer closed.</p>
            </section>
        );
    }

    return (
        <aside
            aria-label="Oracles"
            style={{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem' }}
            data-testid="oracles-drawer"
        >
            <header style={{ display: 'flex', justifyContent: 'space-between' }}>
                <h2>Oracles</h2>
                <button type="button" onClick={onClose}>Close</button>
            </header>

            {loading ? (
                <p aria-busy="true" data-testid="oracles-loading">Loading tables…</p>
            ) : (
                <ul data-testid="oracles-list">
                    {oracles.map((oracle) => (
                        <li key={oracle.oracleId}>
                            <span>{oracle.title}</span>{' '}
                            <small>
                                ({oracle.scopeType === 'global' ? 'global' : 'system'} · {oracle.entryCount} entries)
                            </small>{' '}
                            <button
                                type="button"
                                disabled={consultingOracleId !== null}
                                onClick={() => onConsult(oracle.oracleId)}
                            >
                                {consultingOracleId === oracle.oracleId ? 'Consulting…' : 'Consult'}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {outcome?.status === 'selected' && outcome.entry ? (
                <section
                    aria-label={`Result of ${consultedTitle ?? 'oracle'}`}
                    data-testid="oracle-result"
                    style={{ marginTop: '1rem', borderTop: '1px solid #eee', paddingTop: '0.5rem' }}
                >
                    <p>
                        <strong>{consultedTitle ?? 'Oracle'}:</strong> {outcome.entry.text}
                    </p>

                    {saved ? (
                        <p role="status" data-testid="oracle-saved">Saved to journal.</p>
                    ) : (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                const field = new FormData(event.currentTarget);
                                const interpretation = String(field.get('interpretation') ?? '');
                                onSave(outcome.entry?.text ?? '', interpretation);
                            }}
                        >
                            <label htmlFor="oracle-interpretation">Interpretation</label>
                            <textarea id="oracle-interpretation" name="interpretation" rows={2} />

                            <button type="submit" disabled={saving}>
                                {saving ? 'Saving…' : 'Save to journal'}
                            </button>
                        </form>
                    )}
                </section>
            ) : null}

            {outcome?.status === 'empty_table' ? (
                <p role="status" data-testid="oracle-empty-table">
                    This table is empty — nothing to draw from yet.
                </p>
            ) : null}

            {outcome?.status === 'unavailable' ? (
                <p role="alert" data-testid="oracle-unavailable">
                    This oracle is not available to this campaign.
                </p>
            ) : null}
        </aside>
    );
}
