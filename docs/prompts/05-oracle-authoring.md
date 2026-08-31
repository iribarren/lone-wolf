# 05 · Make oracle tables authorable

Wave 3 · after `02-fix-admin-url` and `03-fix-flow-editor` · branch `fix-oracle-authoring` · ~1 d · fixes audit finding **A4** (critical) and restores the deleted US3 acceptance test

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js.

An **oracle** is a titled random table of textual result entries, each with a positive integer
weight. Consulting one returns exactly one entry, chosen in proportion to the weights. An oracle
is scoped either globally (every system sees it) or to exactly one game system, and a system owns
at most one scoped table. Oracles are authored in the EasyAdmin backoffice.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `.specify/memory/constitution.md` — Principle IV, testing discipline
- `docs/audit/spec-compliance.md` §6 finding A4, and §5 for the history below
- `specs/001-solo-ttrpg-assistant/spec.md` — user story US3 and requirements FR-007, FR-008
</context>

<preconditions>
Prompts `02-fix-admin-url.md` and `03-fix-flow-editor.md` must have landed, or you cannot reach
the page this prompt is about to verify it by hand.

The stack must be running and seeded:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:create-admin --email=admin@example.test --password='admin-passphrase'
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
**An admin cannot author the contents of an oracle table.** The backoffice form for an oracle
exposes exactly three fields — *Table title*, *Visibility*, *Scoped system* — and no field of any
kind for the table's weighted result entries.

`backend/src/Oracles/Infrastructure/Admin/OracleCrudController.php:49-69` yields only `title`,
`scopeType` and `scopeSystemId`, even though `persistEntity()` and `updateEntity()` further down
the same class both read `$entityInstance->entries()` and pass them to the application handlers.
The entries are read; they are never editable.

`backend/src/Oracles/Infrastructure/Admin/OracleEntriesType.php` exists but is referenced nowhere
in `src/` or `templates/` — it is dead code, and in any case it is only a raw textarea
(`getParent()` returns `TextareaType`), not a structured editor.

Verified in Chromium on 2026-08-30 at `/admin/oracle/new`:

    form label texts -> ["Table title", "Visibility", "Scoped system"]
    has entries/weight field -> false

The consequence is quiet rather than loud. An entry-less oracle is a *legal* state that degrades
gracefully — FR-011 requires an empty table to produce a friendly notice rather than an error —
so an admin creates a table, sees no error, and players are told "this table is empty". Today the
only oracle content in the database arrived via `app:seed:demo` or direct SQL.

This makes FR-007 unreachable through its intended surface and user story US3 fail.

**Why it survived.** US3 is the one story with no Behat feature. Commit `211c3e1` ("clean up")
deleted `backend/features/oracles/author_oracle_visibility.feature` and
`backend/tests/Acceptance/Context/OraclesContext.php` in the same diff that marked its tasks
complete; the context file was later restored, the feature never was. The commit immediately
before it, `ccf09a6`, removed the Behat suite entry citing
`Laravel\Lux\Bootstrap\Kernel incompatible with Symfony monorepo` — a class that does not exist,
in a project that is not Laravel. The deleted test is precisely the one that would have caught
this. `specs/001-solo-ttrpg-assistant/tasks.md` still shows the task complete, under a corrupted
id (`X063` rather than `T063`).

You are restoring both the test and the feature it should have protected.
</problem>

<pattern>
**Recover the deleted feature file rather than writing one from scratch.** It is in git history:

    git show 211c3e1^:backend/features/oracles/author_oracle_visibility.feature

It covers three scenarios — a global oracle visible to every system, a system-scoped oracle
visible only to its own system, and a player seeing global ∪ own-system — with entries expressed
inline as `{"text": "...", "weight": N}`. Use it as the starting point and extend it, rather than
inventing different wording; the ubiquitous language in it is already right.

**For the form field, imitate the flow editor, not the dead textarea.** The Rulesets context
already solves exactly this shape of problem — a structured, repeatable collection stored in a
jsonb column:

- `backend/src/Rulesets/Infrastructure/Admin/Form/FlowStageType.php` — one row of the collection,
  with field names matching the jsonb payload keys, and a `CallbackTransformer` that maps a fully
  blank row to `null` so `CollectionType`'s `delete_empty` can drop it instead of failing
  validation
- `backend/src/Rulesets/Infrastructure/Admin/Form/FlowDefinitionType.php` — the collection wrapper
- `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php` — how it is wired into
  an EasyAdmin field
- `backend/tests/Unit/Rulesets/Infrastructure/Form/FlowDefinitionTypeTest.php` — kernel-less unit
  tests over the storage-shape round trip

An oracle entry is `{text: string, weight: int > 0}` — simpler than a flow stage. Follow the same
four-file structure.
</pattern>

<instructions>
1. Confirm the diagnosis still holds. Read `OracleCrudController::configureFields()`, open
   `/admin/oracle/new` in a browser, and check there is still no entries field. If one exists now,
   stop and report.

2. Restore the acceptance test first (Constitution IV, and `AGENTS.md` merge gate 3: "Behat
   features for touched stories green"). Recover the deleted feature with the `git show` command
   above, place it at `backend/features/oracles/authoring.feature`, and reconcile it with the
   current `backend/tests/Acceptance/Context/OraclesContext.php` — the context was restored after
   the deletion and its step definitions may have drifted. Add a fourth scenario covering the
   one-scoped-table-per-system refusal (FR-008), which the original did not have. Confirm the
   feature fails before you touch the controller.

3. Build the entries editor, following the flow-editor pattern above:
   - `backend/src/Oracles/Infrastructure/Admin/Form/OracleEntryType.php` — one row: `text`
     (required, non-blank, max length as enforced by the `Oracle` aggregate) and `weight`
     (integer, minimum 1), with a `CallbackTransformer` dropping fully blank rows.
   - `backend/src/Oracles/Infrastructure/Admin/Form/OracleEntriesCollectionType.php` — the
     collection wrapper, `allow_add`, `allow_delete`, `delete_empty`.
   - Yield the field from `OracleCrudController::configureFields()`.
   - Delete `backend/src/Oracles/Infrastructure/Admin/OracleEntriesType.php`. It is unreferenced
     dead code superseded by the above. (This is removing dead production code, not a test.)

4. **Do not let the field crash the index page.** Commit `08a16c5` — "hide jsonb flow/sheet fields
   from list+detail pages (index crash)" — records that EasyAdmin array fields over jsonb columns
   break the list and detail views. Mark the entries field `onlyOnForms`, exactly as
   `SystemCrudController` does for `sheetStructure`. Verify `/admin/oracle` and an oracle's
   detail page both still render after your change; that is a regression this codebase has
   already had once.

5. Preserve entry identity across edits. `PersistenceOracle.entries` stores each entry with an
   `id` (see the seeded rows: `{"id": "...", "text": "...", "weight": N}`). Check how
   `UpdateOracleHandler` and `Oracle::withEntries()` treat ids, and make sure editing one row's
   text does not silently reissue ids for the others unless the domain intends that. State what
   you found in your report either way.

6. Add kernel-less unit tests for the new form types mirroring `FlowDefinitionTypeTest`: a stored
   payload populates the editor, a submission normalises to the storage shape, blank rows are
   dropped, a malformed payload yields an empty structure, and a zero or negative weight is
   refused by the domain even if the form accepts it.

7. Update the documentation in the same change set (Constitution VI). `docs/functional-guide.md`
   §4.4 carries a "Known defect — this is the big one" block and a documented workaround, and §9
   has a troubleshooting row. Remove both and describe the real authoring flow instead.

8. Repair the task ledger entry. In `specs/001-solo-ttrpg-assistant/tasks.md`, the tasks for this
   work carry corrupted ids `X056`–`X063`. Fix `X063` — the one claiming
   `backend/features/oracles/authoring.feature` — back to `T063` and make its file reference true.
   Leave the other corrupted ids to prompt `10-task-integrity.md`; do not do a bulk rename here.
</instructions>

<constraints>
- Do not change the Oracles domain. `Oracle`, `OracleEntry`, `OracleScope`,
  `WeightedOracleSelector` and their invariants (weight > 0, non-blank text, bounded title) are
  correct and well covered by `backend/tests/Unit/Oracles/`. The domain already refuses bad
  input; the admin form must surface those refusals, not re-implement them.
- Do not change the consult or save endpoints, the scoping SQL, or the partial unique index. US4
  works and is verified.
- Do not add a JSON textarea escape hatch alongside the structured editor. One way to author a
  table.
- Out of scope: bulk import/export of tables, oracle folders, tagging, and any new oracle *kind*
  (odds ladders, yes/no oracles) — see `docs/audit/04-solo-rpg-features.md`, which is deliberately
  a later, separately-specified increment.
</constraints>

<acceptance_criteria>
    docker compose exec php vendor/bin/behat features/oracles/authoring.feature
    # expected: 4 scenarios, all passing

    make lint && make test
    # expected: green, including the new form unit tests

Manually, at `http://localhost:8080/admin/oracle`:
- **Create**: a new oracle with a title, global visibility, and three entries with weights 3/2/1
  saves without error, and the entries persist — verify in the database:
  `docker compose exec postgres psql -U lone_wolf -d lone_wolf -At -c "select title, entries from oracles order by title;"`
- **Edit**: reopening that oracle shows the three rows populated with their stored text and
  weights; changing one weight and saving changes only that weight
- **Delete a row**: removing a row and saving removes exactly that entry
- **Refusal**: a row with weight `0` is refused with a message naming the problem, and nothing is
  persisted
- **No index regression**: `/admin/oracle` (the list) and an oracle's detail page both render
- **End to end**: consult the new table from the player app and see one of its entries returned
</acceptance_criteria>

<completion>
Branch `fix-oracle-authoring` off an updated `master`. Commit atomically with short imperative
subjects; one logical change per commit (`AGENTS.md`: "Task = commit"). The recovered Behat
feature lands, failing, before the controller change.

Before finishing, run and report `make lint`, `make test`, and `npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. This story exists because that rule was broken once already.
Do not create or push git remotes.

Report: what you changed, which gates you ran, what you found about entry-id stability in step 5,
and anything you could not verify.
</completion>
