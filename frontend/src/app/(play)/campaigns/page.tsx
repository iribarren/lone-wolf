'use client';

/**
 * Owned-campaign list (FR-019) with exact-resume entry points (FR-018).
 */
import { useQuery } from '@tanstack/react-query';
import Link from 'next/link';

import type { ApiSchemas } from '@/lib/api/client';
import { useApiClient } from '@/lib/hooks/useApiClient';

type CampaignSummary = ApiSchemas['CampaignSummary'];

export default function CampaignListPage() {
    const api = useApiClient();

    const campaigns = useQuery({
        queryKey: ['campaigns'],
        queryFn: async (): Promise<CampaignSummary[]> => {
            const payload = await api.json('/api/campaigns');

            return Array.isArray(payload) ? (payload as CampaignSummary[]) : [];
        },
    });

    const rows: CampaignSummary[] = campaigns.data ?? [];

    return (
        <main style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '3rem auto' }}>
            <h1>My campaigns</h1>

            {campaigns.isLoading && <p>Loading…</p>}

            {!campaigns.isLoading && rows.length === 0 && (
                <p>
                    Nothing here yet. <Link href="/campaigns/new">Start your first campaign</Link>.
                </p>
            )}

            <ul style={{ listStyle: 'none', padding: 0 }}>
                {rows.map((campaign) => (
                    <li key={campaign.id} style={{ border: '1px solid #ccc', borderRadius: 8, padding: '0.75rem', marginBottom: '0.75rem' }}>
                        <Link href={`/campaigns/${campaign.id}`}>
                            <strong>{campaign.gameSystemName}</strong>
                            <br />
                            <span style={{ color: '#555' }}>
                                Currently at: {campaign.currentStageName}
                            </span>
                            {campaign.updatedAt && (
                                <>
                                    <br />
                                    <small>Updated {new Date(campaign.updatedAt).toLocaleString()}</small>
                                </>
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </main>
    );
}
