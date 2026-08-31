# 10 · Repair and enforce task-ledger integrity

Wave 5 · no dependencies · branch `task-integrity` · ~2 h

<context>
Lone Wolf is a multi-system solo-TTRPG assistant, built with GitHub Spec Kit: each feature lives
in `specs/<feature>/` as `spec.md` → `plan.md` → `tasks.md`, and `AGENTS.md` requires each task to
become one atomic commit prefixed with its task id (`T003: add backend Dockerfile`).

Read before changing anything:
- `AGENTS.md` — the task and commit conventions this prompt enforces
- `docs/audit/spec-compliance.md` §5, "Artifact integrity" — the evidence below
- `specs/001-solo-ttrpg-assistant/tasks.md` — the file being repaired
</context>

<preconditions>
None. This prompt touches specification artifacts and adds a check script; it changes no
application code.
</preconditions>

<problem>
`specs/001-solo-ttrpg-assistant/tasks.md` reports 89 of 89 tasks complete, and cannot be trusted
as evidence of anything. Three defects:

**1. Corrupted task ids.** Lines ~140-149 carry ids `X056`–`X063` instead of `T056`–`T063`.
Commit `211c3e1` ("clean up") renamed them and flipped them from `[ ]` to `[X]` in the same diff.
They no longer match the `T\d+` convention that `AGENTS.md`'s commit rule and every grep depend
on, which makes user story US3 invisible to progress tooling.

**2. A completed task whose deliverable does not exist.** `X063` claims
`backend/features/oracles/authoring.feature`. The file is not on disk. The same `211c3e1` commit
deleted `backend/features/oracles/author_oracle_visibility.feature` (32 lines) and
`backend/tests/Acceptance/Context/OraclesContext.php` (209 lines). The context file was later
restored; the feature never was.

**3. Thirteen never-issued numbers.** T008, T009, T038, T042, T055, T058, T064, T067, T074, T077,
T085, T088, T095 do not exist and never did — a generation artifact, present since the initial
`tasks.md`. Harmless in itself, but it means "all tasks complete" cannot be verified by walking
the id sequence, so the gap has to be recorded rather than rediscovered by every future reader.

Why it matters: the task ledger's only job is to be evidence. The audit found that the twelve
requirements cited by no task overlap heavily with the requirements that turned out not to be
delivered — untraced and unbuilt correlate almost exactly. A ledger that can silently claim a
file exists is not a ledger.
</problem>

<instructions>
1. Confirm the diagnosis still holds:

       grep -nE '^- \[[xX ]\] [^T]' specs/001-solo-ttrpg-assistant/tasks.md
       git show --stat 211c3e1
       ls backend/features/oracles/

   If prompt `05-oracle-authoring.md` has already run, `authoring.feature` will exist and `X063`
   may already be repaired — check, and adjust rather than duplicating that work.

2. Write the check script first, so it fails on the current state and proves the repair.
   `scripts/check-task-integrity.sh`, exiting non-zero when, for any `specs/*/tasks.md`:
   - a task id does not match `^T[0-9]{3}$`
   - a task marked `[x]`/`[X]` contains a backticked repo-relative path that does not exist on disk
   - a task id appears more than once

   It should also print, per phase, the count of open versus complete tasks.

   Match the house style of `scripts/check-contract.sh`: a documented header explaining what it
   gates and every intentional exception, clear exit codes (1 = drift found, 2 = operational
   failure), and no new dependencies beyond bash, grep and coreutils.

3. Run it and capture the output. That output is your to-do list for step 4.

4. Repair `tasks.md`:
   - rename `X056`–`X063` back to `T056`–`T063`
   - for `T063`, either point it at the feature file that now exists (if prompt 05 has run) or
     reopen it as `[ ]` with a note that its deliverable was deleted by `211c3e1`. **Do not leave
     a task marked complete whose file is missing.**
   - add a short note near the top recording the thirteen never-issued numbers, so no future
     reader re-audits them
   - do not otherwise reword, reorder, or re-scope any task

5. Wire the script into CI. If `.github/workflows/ci.yml` exists (prompt `01-ci-pipeline.md`), add
   it as a step. If not, note in your report that it needs adding when CI lands.

6. Document the check in `AGENTS.md` alongside the existing merge gates, in the same change set
   (Constitution VI).
</instructions>

<constraints>
- Do not mark anything complete that you have not verified on disk. That is the exact failure
  being repaired.
- Do not rewrite git history. `211c3e1` and `ccf09a6` stay in the log; they are evidence.
- Do not add, remove or re-scope tasks. Converging unlogged work into `tasks.md` is prompt
  `13-converge-increment.md`; this prompt only repairs integrity of what is already there.
- Do not touch `spec.md`, `plan.md`, `data-model.md` or `research.md`.
</constraints>

<acceptance_criteria>
    bash scripts/check-task-integrity.sh
    # expected: exit 0, with a per-phase open/complete summary

    grep -cE '^- \[[xX]\] X[0-9]{3}' specs/001-solo-ttrpg-assistant/tasks.md
    # expected: 0

    # every completed task's cited paths exist — the script proves this, but spot-check:
    grep -oE '`(backend|frontend|specs|docker|scripts|docs)/[A-Za-z0-9_./-]+`' \
      specs/001-solo-ttrpg-assistant/tasks.md | tr -d '`' | sort -u | while read -r p; do
        [ -e "$p" ] || echo "MISSING: $p"; done
    # expected: no output, or only paths belonging to tasks you deliberately reopened

    make lint && make test
    # expected: unchanged and green

Deliberately corrupt one id and one path, confirm the script fails on each, then revert. Say in
your report that you performed this check.
</acceptance_criteria>

<completion>
Branch `task-integrity` off an updated `master`. Commit atomically with short imperative subjects;
the script lands before the repair so the repair is demonstrably driven by it.

Before finishing, run and report `make lint`, `make test`, and `scripts/check-task-integrity.sh`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass. Do not create or push git remotes.

Report: what you repaired, what the script found before and after, the result of the
deliberate-corruption check, and whether `T063` ended up complete or reopened.
</completion>
