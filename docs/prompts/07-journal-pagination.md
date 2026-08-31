# 07 · Let the journal page back through its history

Wave 4 · after `01-ci-pipeline` · branch `journal-pagination` · ~half a day · fixes audit finding **B3** (medium)

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Players run campaigns along a graph of named
stages and journal as they go; every entry is stamped with the stage that was active when it was
written, and the journal is the artefact the player keeps. Monorepo: `backend/` is Symfony 7.4 +
API Platform, `frontend/` is Next.js talking to it only through the generated client in
`frontend/src/lib/api/`.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `docs/audit/spec-compliance.md` §6 finding B3
- `specs/001-solo-ttrpg-assistant/spec.md` — FR-017 and success criterion SC-008
</context>

<preconditions>
The stack must be running, and you need a campaign with more than one page of entries. The repo
ships a seeder for exactly this:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:seed:demo
    docker compose exec php bin/console app:seed:large-journal --entries=500
    # creates player perf@example.com / perf-player-password on a "Perf Sandbox" campaign

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
**The journal only ever shows the 50 most recent entries, and there is no way to see older ones.**

The backend paginates properly. `ListJournalEntriesQuery.php:23` defaults to `limit = 50`;
`JournalPageProvider` reads `?stageId=` and `?cursor=` from the request; and the response envelope
`JournalPageResource` carries `{entries, nextCursor}` where `nextCursor` is an opaque base64
keyset cursor. Reads are indexed on `(campaign_id, created_at DESC)` so deep pages stay fast.

The frontend never uses any of it. `frontend/src/app/(play)/campaigns/[id]/page.tsx:84-88`:

    const journal = useQuery({
        queryKey: ['campaign', campaignId, 'journal'],
        enabled: campaignId !== '',
        queryFn: async (): Promise<JournalPage> =>
            (await api.json(apiPath(`/api/campaigns/${campaignId}/journal`))) as JournalPage,
    });

No cursor is ever sent, and `JournalTimeline` renders no "load more" control. `nextCursor` is
received and discarded.

Why it matters: FR-017 requires the journal to be "viewable chronologically, grouped by flow
stage". Past 50 entries it is not viewable at all. It also undercuts the SC-008 evidence — the
audit measured a 500-entry journal's latest view at 0.122 s, but that is the only page the UI can
ever request, so the measured path is not the user's path.
</problem>

<pattern>
TanStack Query v5 is already a dependency and already configured in
`frontend/src/app/Providers.tsx` (`staleTime: 30_000, retry: 1, refetchOnWindowFocus: false`).
Use `useInfiniteQuery` with `nextCursor` as the page parameter — that is what the backend's
envelope was designed for. `getNextPageParam` returns `lastPage.nextCursor`, and the terminal
page returns `null`, which is how you know there is no more history.

Every mutation in `page.tsx` that appends to the journal currently calls `journal.refetch()`.
Check what that does to an infinite query with several pages loaded before you copy the pattern —
appending an entry must not silently collapse the reader back to page one.

`JournalTimeline.tsx` groups entries by stage with a `Map` and sorts within each bucket. It takes
a flat `entries` array; keep that contract and flatten the pages in the page component, so the
component's existing Vitest coverage stays meaningful.
</pattern>

<instructions>
1. Confirm the diagnosis still holds. Seed the 500-entry journal, sign in as `perf@example.com`,
   open the campaign, and count the entries rendered. Then call the API by hand and confirm
   `nextCursor` is non-null and that passing it back returns the next page:

       curl -s -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
         "$BASE/api/campaigns/$CID/journal" | jq '{count: (.entries|length), nextCursor}'

2. Write the failing tests first:
   - a Vitest case for whatever component owns the control: a "load more" affordance appears when
     `nextCursor` is present and is absent when it is `null`
   - a Playwright case on the 500-entry campaign: the oldest entry is reachable by loading more,
     and the control disappears at the end of the history

3. Convert the journal query in `page.tsx` to `useInfiniteQuery`, sending `?cursor=` for
   subsequent pages and flattening pages into the flat array `JournalTimeline` expects.

4. Add the "load more" control. It must be a real `<button>` with an accessible name, show a
   pending state while fetching, and disappear — not merely disable — when `nextCursor` is
   `null`, so "you have reached the beginning" is unambiguous. Follow the existing components'
   accessibility conventions: the app has no CSS classes and the E2E suite selects by role and
   label.

5. Make appending an entry behave correctly with pages loaded. Writing a new entry must show it
   at the top without discarding the pages the reader has already loaded. Decide deliberately
   between invalidating and a targeted cache update, and say which you chose and why in your
   report.

6. Consider the `?stageId=` filter, which the provider already supports and the UI never uses.
   **Do not build a filter UI in this PR** — but make sure the query key includes any future
   filter so the two do not have to be untangled later. Note it as a follow-up.

7. Update `docs/functional-guide.md` in the same change set (Constitution VI): §5.4 carries a
   note that the journal loads 50 entries with no way to page back, §8 lists B3 as a known gap,
   and §9 may have a related row. Remove them.
</instructions>

<constraints>
- Backend changes are out of scope. The pagination, the cursor encoding, the index and the
  50-entry default are correct and covered by
  `backend/tests/Integration/Campaigns/PersistenceResumeTest.php` ("pagination cursor walks whole
  history"). Do not change the default limit — if you think 50 is wrong, report it.
- Do not introduce infinite scroll. An explicit control is better for a document the user reads
  and re-reads, and it is testable.
- Do not hand-edit `frontend/src/lib/api/schema.gen.ts`.
- Out of scope: journal search, date filtering, export, and the stage filter UI.
- Out of scope: visual design — match the surrounding inline-style approach so prompts 18–19 can
  restyle everything at once.
</constraints>

<acceptance_criteria>
    npm run test && npm run typecheck && npm run lint
    npm run test:e2e
    make lint && make test
    scripts/check-journal-performance.sh
    # expected: all green; the SC-008 script still passes under 2 s

Manually, on the 500-entry `perf@example.com` campaign:
- the first render shows the newest entries and a "load more" control
- loading repeatedly reaches the oldest entry, and the control then disappears
- stage grouping stays correct across a page boundary — an entry does not jump groups
- no duplicate entries appear at a page seam
- writing a new entry with three pages loaded shows it at the top and does not collapse the view
  back to page one
- the console is free of React key warnings
</acceptance_criteria>

<completion>
Branch `journal-pagination` off an updated `master`. Commit atomically with short imperative
subjects; one logical change per commit (`AGENTS.md`: "Task = commit"). Tests land before the
implementation.

Before finishing, run and report `make lint`, `make test`, `npm run test:e2e` and
`scripts/check-journal-performance.sh`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, how you handled the append-with-pages-loaded case,
and anything you could not verify.
</completion>
