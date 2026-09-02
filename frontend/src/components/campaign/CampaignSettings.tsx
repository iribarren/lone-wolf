'use client';

/**
 * Campaign settings: deletion with typed confirmation and an
 * irreversibility notice (T051, FR-020).
 */
import { useState } from 'react';


import styles from './CampaignSettings.module.css';
export interface CampaignSettingsProps {
    campaignId: string;
    disabled?: boolean;
    pending?: boolean;
    onDelete: () => void;
}

const CONFIRM_PHRASE = 'DELETE';

export default function CampaignSettings({ campaignId, disabled = false, pending = false, onDelete }: CampaignSettingsProps) {
    const [confirmation, setConfirmation] = useState('');
    const [revealed, setRevealed] = useState(false);

    if (!revealed) {
        return (
            <details className={styles.settings}>
                <summary>Campaign settings</summary>
                <button type="button" onClick={() => setRevealed(true)} className={styles.reveal}>
                    Delete this campaign…
                </button>
            </details>
        );
    }

    return (
        <section aria-label="Campaign settings" className={styles.zone}>
            <h3 className={styles.zoneTitle}>Danger zone</h3>
            <p>
                Deleting campaign <code>{campaignId}</code> removes its stage history{' '}
                <strong>and its entire journal, permanently. This cannot be undone.</strong>
            </p>
            <label>
                Type {CONFIRM_PHRASE} to confirm
                <input
                    type="text"
                    value={confirmation}
                    onChange={(e) => setConfirmation(e.target.value)}
                    placeholder={CONFIRM_PHRASE}
                    className={styles.confirmField}
                />
            </label>
            <div className={styles.controls}>
                <button
                    type="button"
                    disabled={disabled || pending || confirmation !== CONFIRM_PHRASE}
                    onClick={onDelete}
                >
                    {pending ? 'Deleting…' : 'Delete permanently'}
                </button>
                <button
                    type="button"
                    onClick={() => {
                        setRevealed(false);
                        setConfirmation('');
                    }}
                >
                    Cancel
                </button>
            </div>
        </section>
    );
}
