'use client';

/**
 * Campaign settings: deletion with typed confirmation and an
 * irreversibility notice (T051, FR-020).
 */
import { useState } from 'react';

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
            <details style={{ marginTop: '2rem' }}>
                <summary>Campaign settings</summary>
                <button type="button" onClick={() => setRevealed(true)} style={{ color: '#b00020' }}>
                    Delete this campaign…
                </button>
            </details>
        );
    }

    return (
        <section aria-label="Campaign settings" style={{ marginTop: '2rem', border: '1px solid #b00020', borderRadius: 8, padding: '1rem' }}>
            <h3 style={{ color: '#b00020' }}>Danger zone</h3>
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
                    style={{ display: 'block', marginTop: '0.25rem' }}
                />
            </label>
            <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.75rem' }}>
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
