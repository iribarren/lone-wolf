'use client';

/**
 * Narrative composer keyed to the campaign's current stage (T052, FR-017).
 */
import { useState, type FormEvent } from 'react';


import styles from './EntryComposer.module.css';
export interface EntryComposerProps {
    stageName?: string;
    disabled?: boolean;
    pending?: boolean;
    error?: string | null;
    onSubmit: (narrative: string) => void;
}

export default function EntryComposer({ stageName, disabled = false, pending = false, error = null, onSubmit }: EntryComposerProps) {
    const [narrative, setNarrative] = useState('');

    function submit(event: FormEvent) {
        event.preventDefault();
        const text = narrative.trim();

        if (text === '') {
            return;
        }

        onSubmit(text);
        setNarrative('');
    }

    return (
        <form onSubmit={submit} aria-label="Journal entry composer" className={styles.composer}>
            <label>
                Record what happened{stageName ? ` at “${stageName}”` : ''}
                <textarea
                    value={narrative}
                    onChange={(e) => setNarrative(e.target.value)}
                    rows={3}
                    disabled={disabled || pending}
                    className={styles.field}
                />
            </label>

            {error && (
                <p role="alert" className={styles.error}>
                    {error}
                </p>
            )}

            <button type="submit" disabled={disabled || pending || narrative.trim() === ''}>
                {pending ? 'Saving…' : 'Add journal entry'}
            </button>
        </form>
    );
}
