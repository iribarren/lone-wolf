# 13 · Converge the untracked increment work

Wave 5 · after `10-task-integrity` · branch `converge-increment` · ~2 h

<context>
Lone Wolf is a multi-system solo-TTRPG assistant built with GitHub Spec Kit. Work is specified in
`specs/<feature>/` as `spec.md` → `plan.md` → `tasks.md`, and `AGENTS.md` requires every task to
become one atomic commit prefixed with its task id. A `speckit-converge` skill exists precisely to
assess the codebase against a feature's artifacts and append remaining or unlogged work back into
`tasks.md`.

Read before changing anything:
- `AGENTS.md` — the task and PR conventions
- `docs/audit/02-specs.md` §2.2.2 — the analysis this prompt comes from
- `docs/audit/spec-compliance.md` §5 and §6 — the defects the untracked work introduced
- `specs/001-solo-ttrpg-assistant/tasks.md`
</context>

<preconditions>
Run `10-task-integrity.md` first, so you are appending to a ledger whose existing ids are valid.

None otherwise — this prompt changes specification artifacts, not application code.
</preconditions>

<problem>
`specs/001-solo-ttrpg-assistant/tasks.md` reports 89 of 89 tasks complete. Since it was last
touched, eleven commits of real feature work have shipped and **not one task was added**:

    32e18b7  admin-login: session form_login firewall + Identity sign-in page
    d827901  admin-login: failing integration spec for backoffice session login
    6990e3c  docs: backoffice sign-in form
    08a16c5  admin-fix: hide jsonb flow/sheet fields from list+detail pages (index crash)
    ef205e2  specs(plan): campaign-flows editor increment design artifacts
    95ae477  flow-editor: structured FlowDefinition form types
    5459d2b  flow-editor: dedicated Campaign flows admin section
    1511584  docs: backoffice campaign-flows section and systems/flows split

The design artifacts *were* updated by hand — `ef205e2` appended decision R11 to `research.md`, a
section to `data-model.md`, a validation section to `quickstart.md`, and created
`contracts/admin-backoffice.md`. The instinct was right. But the work never entered the task
ledger, so it was never gated, and it shipped three critical regressions: A1 (the backoffice
became unreachable), A2 and A3 (the flow editor it added does not work).

So `tasks.md` simultaneously claims to be complete and describes less work than was actually done,
while the undescribed work is where every critical defect lives.
</problem>

<instructions>
1. Read the eight commits above (`git show --stat <sha>`) and establish what each actually
   changed. Read `research.md` R11, the appended `data-model.md` section, the appended
   `quickstart.md` section, and `contracts/admin-backoffice.md` — these describe the *intent* of
   the increment and are your specification input.

2. Run `/speckit-converge` for `specs/001-solo-ttrpg-assistant`.

3. Then hand-verify its output, because a converge that mis-describes the work is the same
   failure again. Check that:
   - each of the eight commits is represented by at least one task, with a valid `T0nn` id
     continuing the existing sequence, and an accurate file list
   - each new task is marked complete **only** where its deliverable exists on disk and works
   - `tasks.md`'s completion count is honest afterwards

4. Add open tasks for the regressions the increment introduced. Each gets a task with a failing
   test named, cross-referenced to its audit finding and to the prompt that fixes it:
   - **A1** — the backoffice is unreachable at `/admin`; fixed by `docs/prompts/02-fix-admin-url.md`
   - **A2** — the flow editor's stage selects are empty on load; `docs/prompts/03-fix-flow-editor.md`
   - **A3** — those selects offer the system's own name; `docs/prompts/03-fix-flow-editor.md`

   If those prompts have already run, mark the tasks complete and cite the commits — but verify
   on disk rather than trusting this prompt.

5. Record a task for the increment's missing acceptance coverage. The campaign-flows editor
   shipped with no Behat feature and no admin E2E test; `quickstart.md`'s appended section
   describes seven **manual** validation steps. Convert that into an open task for automated
   coverage rather than leaving a manual checklist as the only gate.

6. Add a short note to `tasks.md` explaining that tasks beyond the original Phase 9 come from
   converged increment work, so the numbering discontinuity is self-explanatory.

7. Run `scripts/check-task-integrity.sh` (from prompt 10) and confirm it passes over your
   additions.
</instructions>

<constraints>
- Do not mark anything complete you have not verified on disk and, where it is user-facing, in a
  browser. The increment being converged is the exact case where "marked complete" and "actually
  works" came apart.
- Do not fix A1, A2 or A3 here. This prompt logs them; prompts 02 and 03 fix them. Mixing the two
  produces a PR that both defines and closes its own scope, which is how this happened.
- Do not rewrite or re-scope the original 89 tasks.
- Do not edit `spec.md`. If the increment introduced behaviour no requirement covers, report that
  — it may warrant a new FR or its own `specs/002-…`, which is a spec decision, not a converge.
- Do not rewrite git history.
</constraints>

<acceptance_criteria>
    bash scripts/check-task-integrity.sh
    # expected: exit 0

    # every one of the eight commits is discoverable from tasks.md
    for sha in 32e18b7 d827901 6990e3c 08a16c5 ef205e2 95ae477 5459d2b 1511584; do
      git show -s --format='%s' $sha; done
    # expected: for each, you can name the task in tasks.md that now covers it

    make lint && make test
    # expected: unchanged and green

`tasks.md` no longer reports 100% complete, because A1/A2/A3 are open (or, if prompts 02–03 have
already landed, it reports complete and every cited file exists and works).

Every new task cites the FR it serves, so prompt `12-traceability-matrix.md` can pick it up.
</acceptance_criteria>

<completion>
Branch `converge-increment` off an updated `master`. Commit atomically with short imperative
subjects.

Before finishing, run and report `make lint`, `make test` and `scripts/check-task-integrity.sh`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass. Do not create or push git remotes.

Report: the tasks you added, which you marked complete and on what evidence, which you left open,
and anything the increment did that no requirement in `spec.md` covers.
</completion>
