'use client';

/**
 * GM console (T051, FR-014–FR-018): exact-resume state fetch, stage guidance,
 * advance controls with refusal feedback, and destructive settings.
 */
import { useMutation, useQuery } from '@tanstack/react-query';
import { useParams, useRouter } from 'next/navigation';
import { useState } from 'react';

import AdvanceActions, { type RefusalFeedback } from '@/components/campaign/AdvanceActions';
import CampaignSettings from '@/components/campaign/CampaignSettings';
import StagePanel from '@/components/campaign/StagePanel';
import EntryComposer from '@/components/journal/EntryComposer';
import JournalTimeline from '@/components/journal/JournalTimeline';
import { ApiError, apiPath, type ApiSchemas } from '@/lib/api/client';
import { useApiClient } from '@/lib/hooks/useApiClient';

type CampaignState = ApiSchemas['CampaignState'];
type JournalEntry = ApiSchemas['JournalEntry'];

interface JournalPage {
    entries: JournalEntry[];
    nextCursor?: string | null;
}

export default function CampaignConsolePage() {
    const api = useApiClient();
    const router = useRouter();
    const params = useParams<{ id: string }>();
    const campaignId = params?.id ?? '';
    const [refusal, setRefusal] = useState<RefusalFeedback | null>(null);

    const campaign = useQuery({
        queryKey: ['campaign', campaignId],
        enabled: campaignId !== '',
        queryFn: async (): Promise<CampaignState> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}`))) as CampaignState,
        retry: false,
    });

    const advance = useMutation({
        mutationFn: async (toStageId: string): Promise<CampaignState> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/advance`), {
                method: 'POST',
                body: { toStageId },
            })) as CampaignState,
        onSuccess: () => {
            setRefusal(null);
            void campaign.refetch();
        },
        onError: (err) => {
            if (err instanceof ApiError) {
                const alternatives = Array.isArray(err.extra?.['legalAlternatives'])
                    ? (err.extra?.['legalAlternatives'] as string[])
                    : [];
                setRefusal({ detail: err.detail ?? err.title, legalAlternatives: alternatives });
            }
        },
    });

    const journal = useQuery({
        queryKey: ['campaign', campaignId, 'journal'],
        enabled: campaignId !== '',
        queryFn: async (): Promise<JournalPage> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/journal`))) as JournalPage,
    });

    const append = useMutation({
        mutationFn: async (narrative: string): Promise<JournalEntry> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/journal`), {
                method: 'POST',
                body: { narrative },
            })) as JournalEntry,
        onSuccess: () => void journal.refetch(),
    });

    const remove = useMutation({
        mutationFn: async (): Promise<void> => {
            await api.request(apiPath(`/api/campaigns/${campaignId}?confirm=true`), {
                method: 'DELETE',
            });
        },
        onSuccess: () => {
            router.push('/campaigns');
        },
    });

    if (campaign.isLoading) {
        return (
            <main style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '3rem auto' }}>
                <p>Loading campaign…</p>
            </main>
        );
    }

    if (campaign.isError || !campaign.data) {
        return (
            <main style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '3rem auto' }}>
                <p role="alert">
                    {campaign.error instanceof ApiError && campaign.error.status === 404
                        ? 'Campaign not found.'
                        : 'Could not load this campaign.'}
                </p>
            </main>
        );
    }

    const state = campaign.data;
    const stage = state.currentStage;
    const actions = stage?.suggestedActions ?? [];

    return (
        <main style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '3rem auto' }}>
            <h1>Game master console</h1>

            <StagePanel
                stageName={stage?.name ?? 'Unknown stage'}
                guidance={stage?.guidance ?? ''}
            />

            <AdvanceActions
                actions={actions}
                disabled={advance.isPending || remove.isPending}
                pending={advance.isPending}
                refusal={refusal}
                onAdvance={(toStageId) => advance.mutate(toStageId)}
                onConclude={() => {
                    const conclude = actions.find((action) => action.kind === 'conclude');
                    if (conclude?.toStageId) {
                        advance.mutate(conclude.toStageId);
                    }
                }}
            />

            <JournalTimeline entries={journal.data?.entries ?? []} loading={journal.isLoading} />

            <EntryComposer
                stageName={stage?.name}
                disabled={append.isPending || advance.isPending || remove.isPending}
                pending={append.isPending}
                error={append.error instanceof ApiError ? append.error.message : null}
                onSubmit={(narrative) => append.mutate(narrative)}
            />

            <CampaignSettings
                campaignId={campaignId}
                disabled={campaign.isFetching}
                pending={remove.isPending}
                onDelete={() => remove.mutate()}
            />
        </main>
    );
}
