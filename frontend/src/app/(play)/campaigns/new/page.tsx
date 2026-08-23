'use client';

/**
 * Campaign creation flow (T050, FR-012): pick an active system, start a
 * campaign on its designated opening stage, then jump into the GM console.
 */
import { useMutation, useQuery } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { useState } from 'react';

import { ApiError, type ApiSchemas } from '@/lib/api/client';
import { useApiClient } from '@/lib/hooks/useApiClient';

type SystemSummary = ApiSchemas['System'];
type CampaignState = ApiSchemas['CampaignState'];

export default function NewCampaignPage() {
    const api = useApiClient();
    const router = useRouter();
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const systems = useQuery({
        queryKey: ['systems'],
        queryFn: async (): Promise<SystemSummary[]> => {
            const payload = await api.json('/api/systems');

            return Array.isArray(payload) ? (payload as SystemSummary[]) : [];
        },
    });

    const create = useMutation({
        mutationFn: async (gameSystemId: string): Promise<CampaignState> =>
            (await api.json('/api/campaigns', {
                method: 'POST',
                body: { gameSystemId },
            })) as CampaignState,
        onSuccess: (state) => {
            if (state.id) {
                router.push(`/campaigns/${state.id}`);
            }
        },
        onError: (err) => {
            setError(err instanceof ApiError ? err.message : 'Creating the campaign failed.');
        },
    });

    const rows: SystemSummary[] = systems.data ?? [];

    return (
        <main style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '3rem auto' }}>
            <h1>Start a campaign</h1>
            <p>Choose the game system your story will follow — this binding is permanent.</p>

            {systems.isLoading && <p>Loading systems…</p>}
            {systems.isError && <p role="alert">Could not load game systems.</p>}

            {!systems.isLoading && rows.length === 0 && (
                <p>No active game systems yet. Ask an admin to author one first.</p>
            )}

            <ul style={{ listStyle: 'none', padding: 0 }}>
                {rows.map((system) => (
                    <li key={system.systemId} style={{ border: '1px solid #ccc', borderRadius: 8, padding: '0.75rem', marginBottom: '0.75rem' }}>
                        <label style={{ display: 'flex', gap: '0.5rem', alignItems: 'flex-start' }}>
                            <input
                                type="radio"
                                name="game-system"
                                value={system.systemId}
                                checked={selectedId === system.systemId}
                                onChange={() => setSelectedId(system.systemId ?? null)}
                            />
                            <span>
                                <strong>{system.name}</strong>
                                <br />
                                <span style={{ color: '#555' }}>{system.description}</span>
                                <br />
                                <small>Opens at: {system.startingStage}</small>
                            </span>
                        </label>
                    </li>
                ))}
            </ul>

            {error && (
                <p role="alert" style={{ color: '#b00020' }}>
                    {error}
                </p>
            )}

            <button
                type="button"
                disabled={!selectedId || create.isPending}
                onClick={() => selectedId && create.mutate(selectedId)}
            >
                {create.isPending ? 'Starting…' : 'Begin campaign'}
            </button>
        </main>
    );
}
