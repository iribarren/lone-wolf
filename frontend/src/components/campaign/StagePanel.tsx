'use client';

import styles from './StagePanel.module.css';
import Card from '@/components/ui/Card';

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
        <Card as="section" aria-label={`Current stage: ${stageName}`}>
            <h2>{stageName}</h2>
            <p className={styles.guidance}>{guidance}</p>
        </Card>
    );
}
