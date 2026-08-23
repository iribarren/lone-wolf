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
import CharacterPanel, { type CharacterPanelCharacter } from '@/components/characters/CharacterPanel';
import EntryComposer from '@/components/journal/EntryComposer';
import JournalTimeline from '@/components/journal/JournalTimeline';
import OracleDrawer, {
    type ConsultationOutcomeView,
    type OracleSummaryView,
} from '@/components/oracles/OracleDrawer';
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
    const [oraclesOpen, setOraclesOpen] = useState(false);
    const [consultingOracleId, setConsultingOracleId] = useState<string | null>(null);
    const [consulted, setConsulted] = useState<{
        oracleId: string;
        title: string;
        outcome: ConsultationOutcomeView;
    } | null>(null);

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

    const oracles = useQuery({
        queryKey: ['campaign', campaignId, 'oracles'],
        enabled: campaignId !== '' && oraclesOpen,
        queryFn: async (): Promise<OracleSummaryView[]> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/oracles`))) as OracleSummaryView[],
    });

    const characters = useQuery({
        queryKey: ['campaign', campaignId, 'characters'],
        enabled: campaignId !== '',
        queryFn: async (): Promise<CharacterPanelCharacter[]> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/characters`))) as CharacterPanelCharacter[],
    });

    async function consult(oracleId: string): Promise<void> {
        setConsultingOracleId(oracleId);
        setConsulted(null);

        try {
            const outcome = (await api.json(
                apiPath(`/api/campaigns/${campaignId}/oracles/${oracleId}/consult`),
                { method: 'POST', body: {} },
            )) as ConsultationOutcomeView;

            const title =
                oracles.data?.find((table) => table.oracleId === oracleId)?.title ?? 'Oracle';
            setConsulted({ oracleId, title, outcome });
        } finally {
            setConsultingOracleId(null);
        }
    }

    const saveResult = useMutation({
        mutationFn: async ({ text, interpretation }: { text: string; interpretation: string }): Promise<void> => {
            await api.json(apiPath(`/api/campaigns/${campaignId}/oracles/${consulted?.oracleId ?? ''}/save`), {
                method: 'POST',
                body: { text, interpretation },
            });
        },
        onSuccess: () => void journal.refetch(),
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

            <CharacterPanel
                characters={characters.data ?? []}
                loading={characters.isLoading}
                violations={[]}
            />

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

            <div style={{ position: 'fixed', right: '1rem', bottom: '1rem' }}>
                <button type="button" onClick={() => setOraclesOpen((open) => !open)}>
                    {oraclesOpen ? 'Hide oracles' : 'Oracles'}
                </button>
            </div>

            <OracleDrawer
                open={oraclesOpen}
                oracles={oracles.data ?? []}
                loading={oracles.isLoading}
                consultingOracleId={consultingOracleId}
                consultedTitle={consulted?.title ?? null}
                outcome={consulted?.outcome ?? null}
                saving={saveResult.isPending}
                saved={saveResult.isSuccess && consulted !== null}
                onClose={() => {
                    setOraclesOpen(false);
                    setConsulted(null);
                    saveResult.reset();
                }}
                onConsult={(oracleId) => void consult(oracleId)}
                onSave={(text, interpretation) =>
                    saveResult.mutate({ text, interpretation })
                }
            />
        </main>
    );
}
