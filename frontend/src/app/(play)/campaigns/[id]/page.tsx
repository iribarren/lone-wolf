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
import DiceRollerWidget, {
    type DiceProblemView,
    type DiceRollResultView,
} from '@/components/dice/DiceRollerWidget';
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
    const [diceOpen, setDiceOpen] = useState(false);
    const [diceResult, setDiceResult] = useState<DiceRollResultView | null>(null);
    const [diceProblem, setDiceProblem] = useState<DiceProblemView | null>(null);
    const [rollLogged, setRollLogged] = useState(false);
    const [rollingDice, setRollingDice] = useState(false);
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

    async function roll(notation: string): Promise<void> {
        setDiceProblem(null);
        setDiceResult(null);
        setRollLogged(false);
        setRollingDice(true);

        try {
            // Invalid notation is refused pre-roll with a typed reason —
            // never a fake result (FR-027).
            const rolled = (await api.json(apiPath('/api/dice/roll'), {
                method: 'POST',
                body: { notation },
            })) as DiceRollResultView;
            setDiceResult(rolled);
        } catch (err) {
            if (err instanceof ApiError) {
                const reason = err.extra?.['reason'];
                setDiceProblem({
                    reason: (
                        reason === 'invalid_count' ||
                        reason === 'invalid_faces' ||
                        reason === 'out_of_bounds'
                    )
                        ? reason
                        : 'malformed',
                    detail: err.detail,
                });
            }
        } finally {
            setRollingDice(false);
        }
    }

    const logRoll = useMutation({
        // The logged-roll endpoint performs the roll AND journals it, so the
        // shown result is replaced by exactly what the journal recorded.
        mutationFn: async (): Promise<{ roll: DiceRollResultView }> => {
            return (await api.json(apiPath(`/api/campaigns/${campaignId}/rolls`), {
                method: 'POST',
                body: { notation: diceResult?.notation ?? '' },
            })) as { roll: DiceRollResultView };
        },
        onSuccess: (logged) => {
            setDiceResult(logged.roll);
            setRollLogged(true);
            void journal.refetch();
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

            <div style={{ position: 'fixed', right: '1rem', bottom: '1rem', display: 'flex', gap: '0.5rem' }}>
                <button type="button" onClick={() => setDiceOpen((open) => !open)}>
                    {diceOpen ? 'Hide dice' : 'Dice'}
                </button>
                <button type="button" onClick={() => setOraclesOpen((open) => !open)}>
                    {oraclesOpen ? 'Hide oracles' : 'Oracles'}
                </button>
            </div>

            <DiceRollerWidget
                open={diceOpen}
                rolling={rollingDice}
                logging={logRoll.isPending}
                logged={rollLogged}
                result={diceResult}
                problem={diceProblem}
                onClose={() => {
                    setDiceOpen(false);
                    setDiceResult(null);
                    setDiceProblem(null);
                    setRollLogged(false);
                }}
                onRoll={(notation) => void roll(notation)}
                onLogResult={() => logRoll.mutate()}
            />

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
