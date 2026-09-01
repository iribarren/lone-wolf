'use client';

/**
 * Stage-grouped chronological journal timeline (T052, FR-015/017).
 */
import type { ApiSchemas } from '@/lib/api/client';

type JournalEntry = ApiSchemas['JournalEntry'];

export interface JournalTimelineProps {
    entries: JournalEntry[];
    loading?: boolean;
    /** Older history remains behind the newest page (the API's nextCursor). */
    hasMore?: boolean;
    loadingMore?: boolean;
    onLoadMore?: () => void;
}

interface EntryGroup {
    stageName: string;
    entries: JournalEntry[];
}

function groupByStage(entries: JournalEntry[]): EntryGroup[] {
    const groups = new Map<string, JournalEntry[]>();

    for (const entry of entries) {
        const key = entry.stageName ?? entry.stageId ?? 'Unknown stage';
        const bucket = groups.get(key) ?? [];
        bucket.push(entry);
        groups.set(key, bucket);
    }

    return [...groups.entries()].map(([stageName, grouped]) => ({
        stageName,
        entries: [...grouped].sort((a, b) => (a.createdAt ?? '').localeCompare(b.createdAt ?? '')),
    }));
}

export default function JournalTimeline({
    entries,
    loading = false,
    hasMore = false,
    loadingMore = false,
    onLoadMore,
}: JournalTimelineProps) {
    if (loading) {
        return (
            <section aria-busy="true" data-testid="journal-loading">
                <p>Loading journal…</p>
            </section>
        );
    }

    if (entries.length === 0) {
        return (
            <section aria-label="Journal" style={{ marginTop: '2rem' }}>
                <h2>Journal</h2>
                <p>Nothing recorded yet — your story starts below.</p>
            </section>
        );
    }

    return (
        <section aria-label="Journal" style={{ marginTop: '2rem' }}>
            <h2>Journal</h2>

            {groupByStage(entries).map((group) => (
                <div key={group.stageName} style={{ marginBottom: '1rem' }}>
                    <h3 style={{ borderBottom: '1px solid #ccc', paddingBottom: '0.25rem' }}>{group.stageName}</h3>
                    <ol style={{ listStyle: 'none', padding: 0 }}>
                        {group.entries.map((entry) => (
                            <li key={entry.id} style={{ padding: '0.5rem 0', borderBottom: '1px dashed #ddd' }}>
                                <small style={{ color: '#555' }}>
                                    {entry.createdAt ? new Date(entry.createdAt).toLocaleString() : ''}
                                    {entry.kind === 'oracle_roll' ? ' · oracle roll' : ''}
                                </small>
                                {entry.narrative && <p style={{ margin: '0.25rem 0 0' }}>{entry.narrative}</p>}
                            </li>
                        ))}
                    </ol>
                </div>
            ))}

            {/*
              * Explicit control, and absent rather than disabled once the
              * cursor runs out: "you have reached the beginning" must not be
              * mistakable for "this button is broken" (B3).
              */}
            {hasMore && onLoadMore && (
                <button type="button" onClick={onLoadMore} disabled={loadingMore}>
                    {loadingMore ? 'Loading earlier entries…' : 'Load earlier entries'}
                </button>
            )}
        </section>
    );
}
