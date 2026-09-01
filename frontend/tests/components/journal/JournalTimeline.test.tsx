import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import JournalTimeline from '@/components/journal/JournalTimeline';
import type { ApiSchemas } from '@/lib/api/client';

type JournalEntry = ApiSchemas['JournalEntry'];

function entry(id: string, stageName: string, createdAt: string, narrative: string): JournalEntry {
    return { id, stageId: stageName, stageName, kind: 'narrative', narrative, createdAt };
}

const newest = entry('e3', 'Sequel', '2026-08-30T12:00:00+00:00', 'The newest thing that happened.');
const oldest = entry('e1', 'Scene', '2026-08-30T10:00:00+00:00', 'The oldest thing that happened.');

describe('JournalTimeline', () => {
    it('offers a load-more control while older history remains', () => {
        const onLoadMore = vi.fn();
        render(<JournalTimeline entries={[newest, oldest]} hasMore onLoadMore={onLoadMore} />);

        fireEvent.click(screen.getByRole('button', { name: 'Load earlier entries' }));

        expect(onLoadMore).toHaveBeenCalledTimes(1);
    });

    it('removes the control at the beginning of the history rather than disabling it', () => {
        render(<JournalTimeline entries={[newest, oldest]} hasMore={false} onLoadMore={vi.fn()} />);

        // Absent, not disabled: a greyed-out button reads as "broken", where a
        // missing one reads as "you have reached the beginning".
        expect(screen.queryByRole('button', { name: /earlier entries/i })).not.toBeInTheDocument();
    });

    it('shows a pending state while an earlier page is loading', () => {
        render(<JournalTimeline entries={[newest, oldest]} hasMore loadingMore onLoadMore={vi.fn()} />);

        expect(screen.getByRole('button', { name: 'Loading earlier entries…' })).toBeDisabled();
    });

    it('keeps entries in their own stage group across a page seam', () => {
        // Two pages flattened: the seam falls inside the "Scene" run, and an
        // entry must not migrate to the group of the page it arrived with.
        const flattened = [
            entry('p1-b', 'Sequel', '2026-08-30T12:00:00+00:00', 'Page one, sequel.'),
            entry('p1-a', 'Scene', '2026-08-30T11:00:00+00:00', 'Page one, scene.'),
            entry('p2-b', 'Scene', '2026-08-30T10:00:00+00:00', 'Page two, scene.'),
            entry('p2-a', 'Sequel', '2026-08-30T09:00:00+00:00', 'Page two, sequel.'),
        ];

        render(<JournalTimeline entries={flattened} hasMore={false} onLoadMore={vi.fn()} />);

        const groups = screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent);
        expect(groups).toEqual(['Sequel', 'Scene']);

        const sequel = screen.getByRole('heading', { level: 3, name: 'Sequel' }).parentElement;
        const scene = screen.getByRole('heading', { level: 3, name: 'Scene' }).parentElement;
        expect(sequel).toHaveTextContent('Page two, sequel.');
        expect(scene).toHaveTextContent('Page two, scene.');
        expect(scene).not.toHaveTextContent('Page two, sequel.');
    });
});
