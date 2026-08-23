'use client';

/**
 * Guidance card for the campaign's current stage (T051, FR-013).
 * Pure presentational component so states are directly testable (T053).
 */
export interface StagePanelProps {
    stageName: string;
    guidance: string;
    loading?: boolean;
}

export default function StagePanel({ stageName, guidance, loading = false }: StagePanelProps) {
    if (loading) {
        return (
            <section aria-busy="true" data-testid="stage-panel-loading">
                <p>Loading stage…</p>
            </section>
        );
    }

    return (
        <section
            aria-label={`Current stage: ${stageName}`}
            style={{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem' }}
        >
            <h2>{stageName}</h2>
            <p style={{ whiteSpace: 'pre-line' }}>{guidance}</p>
        </section>
    );
}
