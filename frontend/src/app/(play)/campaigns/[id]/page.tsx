'use client';

/**
 * GM console (T051, FR-014–FR-018): exact-resume state fetch, stage guidance,
 * advance controls with refusal feedback, and destructive settings.
 */
import { useInfiniteQuery, useMutation, useQuery, useQueryClient, type InfiniteData } from '@tanstack/react-query';
import { useParams, useRouter } from 'next/navigation';
import { useState } from 'react';

import AdvanceActions, { type RefusalFeedback } from '@/components/campaign/AdvanceActions';
import CampaignSettings from '@/components/campaign/CampaignSettings';
import StagePanel from '@/components/campaign/StagePanel';
import CharacterForm, { type CharacterDraft } from '@/components/characters/CharacterForm';
import CharacterPanel, {
    type CharacterPanelCharacter,
    type SheetViolation,
} from '@/components/characters/CharacterPanel';
import DiceRollerWidget, {
    isDiceRollResultView,
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

/** The cursor of the page to fetch; `null` asks for the newest one. */
type JournalCursor = string | null;
type JournalPages = InfiniteData<JournalPage, JournalCursor>;

/**
 * Sheet refusals name a `field`, where the generic problem parser expects a
 * `property` — read both rather than let the shape decide whether a player
 * sees the message (contract SheetValidationProblem, FR-023).
 */
function sheetViolationsOf(error: unknown): SheetViolation[] {
    if (!(error instanceof ApiError) || !Array.isArray(error.violations)) {
        return [];
    }

    return error.violations.flatMap((raw): SheetViolation[] => {
        const entry = raw as { field?: unknown; property?: unknown; message?: unknown };
        const field = typeof entry.field === 'string'
            ? entry.field
            : typeof entry.property === 'string'
                ? entry.property
                : null;

        return field === null || typeof entry.message !== 'string'
            ? []
            : [{ field, message: entry.message }];
    });
}

export default function CampaignConsolePage() {
    const api = useApiClient();
    const queryClient = useQueryClient();
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
    const [characterFormOpen, setCharacterFormOpen] = useState(false);
    const [editingCharacter, setEditingCharacter] = useState<CharacterPanelCharacter | null>(null);
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

    // The API's `?stageId=` filter has no UI yet (B3 follow-up); it sits in
    // the key from the start so a filtered view can never be served from the
    // unfiltered cache once one is built.
    const journalKey = ['campaign', campaignId, 'journal', { stageId: null }];

    const journal = useInfiniteQuery({
        queryKey: journalKey,
        enabled: campaignId !== '',
        initialPageParam: null as JournalCursor,
        queryFn: async ({ pageParam }): Promise<JournalPage> => {
            const query = pageParam === null ? '' : `?cursor=${encodeURIComponent(pageParam)}`;

            return (await api.json(apiPath(`/api/campaigns/${campaignId}/journal${query}`))) as JournalPage;
        },
        // A terminal page carries no cursor — that is how the reader learns
        // there is no more history behind it (FR-017).
        getNextPageParam: (lastPage): JournalCursor => lastPage.nextCursor ?? null,
    });

    // JournalTimeline groups a flat list, so the pages are flattened here and
    // its contract — and its tests — stay as they were.
    const journalEntries = journal.data?.pages.flatMap((page) => page.entries) ?? [];

    /**
     * Shows freshly written entries at the top of the newest page.
     *
     * Deliberately not `refetch()`/`invalidateQueries()`: those re-request
     * every loaded page against its stored keyset cursor, so a new entry
     * pushes one entry out of the newest page while the next page's cursor
     * stays where it was — the entry at the seam disappears from the view —
     * and it costs one request per page the reader has opened. Writing into
     * the cache leaves the loaded pages and their cursors untouched.
     */
    function showNewEntries(entries: JournalEntry[]): void {
        if (entries.length === 0) {
            return;
        }

        queryClient.setQueryData<JournalPages>(journalKey, (current) => {
            if (!current || current.pages.length === 0) {
                return current;
            }

            const [newest, ...older] = current.pages;
            const known = new Set(newest.entries.map((entry) => entry.id));
            const added = entries.filter((entry) => !known.has(entry.id));

            return {
                ...current,
                pages: [{ ...newest, entries: [...added, ...newest.entries] }, ...older],
            };
        });
    }

    /**
     * Re-reads the newest page only, for writes that answer with the entry's
     * id rather than the entry itself. The pages already paged back through
     * are never re-requested.
     */
    async function showLatestWrites(): Promise<void> {
        const head = (await api.json(apiPath(`/api/campaigns/${campaignId}/journal`))) as JournalPage;
        showNewEntries(head.entries);
    }

    const append = useMutation({
        mutationFn: async (narrative: string): Promise<JournalEntry> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/journal`), {
                method: 'POST',
                body: { narrative },
            })) as JournalEntry,
        onSuccess: (created) => showNewEntries([created]),
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

    // Create and edit share one payload and one refusal shape, so they share
    // one mutation: the id decides which endpoint takes it (FR-021..FR-023).
    const saveCharacter = useMutation({
        mutationFn: async ({ id, draft }: { id?: string; draft: CharacterDraft }): Promise<unknown> =>
            id === undefined
                ? await api.json(apiPath(`/api/campaigns/${campaignId}/characters`), {
                    method: 'POST',
                    body: draft,
                })
                : await api.json(apiPath(`/api/characters/${id}`), {
                    method: 'PATCH',
                    body: draft,
                }),
        onSuccess: () => {
            setCharacterFormOpen(false);
            setEditingCharacter(null);
            // A conforming save clears drift server-side, so the badge goes
            // with the refetch rather than with a reload (FR-025).
            void characters.refetch();
        },
    });

    function openCharacterForm(character: CharacterPanelCharacter | null): void {
        saveCharacter.reset();
        setEditingCharacter(character);
        setCharacterFormOpen(true);
    }

    function closeCharacterForm(): void {
        saveCharacter.reset();
        setEditingCharacter(null);
        setCharacterFormOpen(false);
    }

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
            const rolled = await api.json(apiPath('/api/dice/roll'), {
                method: 'POST',
                body: { notation },
            });

            if (!isDiceRollResultView(rolled)) {
                setDiceProblem({ reason: 'unreadable_result' });

                return;
            }

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
        mutationFn: async (): Promise<unknown> => {
            return await api.json(apiPath(`/api/campaigns/${campaignId}/rolls`), {
                method: 'POST',
                body: { notation: diceResult?.notation ?? '' },
            });
        },
        onSuccess: (logged) => {
            const payload = logged as { roll?: unknown; journalEntry?: JournalEntry } | null;

            // The logged entry travels with the roll, so the timeline gains it
            // without re-reading anything; an off-contract body still journals
            // server-side, so fall back to re-reading the newest page.
            if (payload?.journalEntry) {
                showNewEntries([payload.journalEntry]);
            } else {
                void showLatestWrites();
            }

            // Nothing off-contract may reach the render (audit A5): the roll
            // is journalled server-side either way, so an unreadable body
            // degrades to the dice error notice, never to a blank page.
            if (!isDiceRollResultView(payload?.roll)) {
                setDiceProblem({ reason: 'unreadable_result' });
                setRollLogged(false);

                return;
            }

            setDiceResult(payload.roll);
            setRollLogged(true);
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
    // The sheet shape travels beside the characters; every character of a
    // campaign carries the same one, so the first that has it speaks for all.
    const sheetFields = characters.data?.find((character) => character.structureFields)?.structureFields ?? [];
    const saveProblem = saveCharacter.error instanceof ApiError ? saveCharacter.error : null;
    const sheetViolations = sheetViolationsOf(saveCharacter.error);

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

            <JournalTimeline
                entries={journalEntries}
                loading={journal.isLoading}
                hasMore={journal.hasNextPage}
                loadingMore={journal.isFetchingNextPage}
                onLoadMore={() => void journal.fetchNextPage()}
            />

            <CharacterPanel
                characters={characters.data ?? []}
                loading={characters.isLoading}
                violations={[]}
                onEdit={openCharacterForm}
            />

            {characterFormOpen ? (
                <CharacterForm
                    // Re-key so the form reseeds when the target changes.
                    key={editingCharacter?.id ?? 'new-character'}
                    fields={sheetFields}
                    character={editingCharacter}
                    violations={sheetViolations}
                    error={sheetViolations.length === 0 ? saveProblem?.detail ?? saveProblem?.title ?? null : null}
                    sheetless={saveProblem?.status === 422 && sheetViolations.length === 0}
                    pending={saveCharacter.isPending}
                    onSubmit={(draft) => saveCharacter.mutate({ id: editingCharacter?.id, draft })}
                    onCancel={closeCharacterForm}
                />
            ) : (
                <button type="button" onClick={() => openCharacterForm(null)}>
                    Add a character
                </button>
            )}

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
