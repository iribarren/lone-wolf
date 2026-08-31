# 12 · Build and gate the requirements traceability matrix

Wave 5 · no dependencies · branch `traceability-matrix` · ~half a day

<context>
Lone Wolf is a multi-system solo-TTRPG assistant built with GitHub Spec Kit. Its one feature spec,
`specs/001-solo-ttrpg-assistant/`, defines 6 user stories, 31 functional requirements
(FR-001…FR-031) and 8 success criteria, broken down into 89 tasks in `tasks.md`.

Read before changing anything:
- `specs/001-solo-ttrpg-assistant/spec.md` — the requirements
- `specs/001-solo-ttrpg-assistant/tasks.md` — the task ledger
- `docs/audit/spec-compliance.md` — a verified verdict for every FR, established 2026-08-30
- `docs/audit/02-specs.md` §2.2.1 — the analysis this prompt comes from
</context>

<preconditions>
None. This prompt produces a document and a check script; it changes no application code.

Ideally run after `10-task-integrity.md`, because task ids `X056`–`X063` are corrupted and will
otherwise appear as broken references in your matrix. If it has not run, note those ids as
corrupted rather than silently normalising them.
</preconditions>

<problem>
**Nothing in the repository answers "which test proves FR-014?"**

Only 19 of the 31 functional requirements are cited anywhere in `tasks.md`. The twelve that are
cited by no task at all: FR-001, FR-003, FR-004, FR-008, FR-010, FR-014, FR-017, FR-021, FR-022,
FR-026, FR-027, FR-028.

That would be a documentation gap and little more, except for what the audit found when it traced
every requirement to running code. The requirements that turned out **not to be met** are FR-003,
FR-004 and FR-007; met API-only are FR-021 and FR-023; partial are FR-001 and FR-017. **Five of
those seven sit in the untraced twelve.** Untraced and undelivered correlate almost exactly — a
requirement no task claims is a requirement nobody checks at the end.

The information needed to close this is recoverable by reading the repo, and the audit did
exactly that. What is missing is a durable artifact and a gate, so the next requirement to fall
through the gap fails a build instead of surviving to an audit.
</problem>

<pattern>
`docs/audit/spec-compliance.md` §2 is a worked example of the analysis, requirement by
requirement, with a verified verdict and the backend, frontend and test evidence for each. Your
matrix is the mechanically-checkable form of it, and it must agree with it.

`specs/001-solo-ttrpg-assistant/data-model.md` already annotates fields with the FR each serves —
the closest thing the repo has to traceability today, and a useful cross-check.

For locating implementations, `mcp__codegraph__codegraph_explore` is installed and pre-approved in
`.claude/settings.json`. It returns the source of relevant symbols in one call and is the right
tool here; grep-and-read across 31 requirements is dozens of calls for the same answer.
</pattern>

<instructions>
1. Read `spec.md` and extract all 31 requirements verbatim. Do not paraphrase them into the
   matrix — a paraphrase drifts from what was ratified.

2. For each requirement, derive from the repository (not from assumption):
   - the user story it belongs to
   - the task id(s) in `tasks.md` that cite it, if any
   - the test(s) that prove it — search `backend/tests/Unit`, `backend/tests/Integration`,
     `backend/features`, `frontend/tests/components`, `frontend/tests/e2e`
   - the implementing file(s)

3. Write `specs/001-solo-ttrpg-assistant/traceability.md`: one row per requirement, columns
   `FR | requirement | story | task(s) | test(s) | implementation | status`.

   Status is exactly one of: `COVERED` (task and test and code), `NO-TEST`, `NO-TASK`,
   `NOT-IMPLEMENTED`.

4. Reconcile against `docs/audit/spec-compliance.md` §2, which carries a verdict for every FR
   verified against a running stack. Where your derivation and the audit disagree, **flag the
   disagreement explicitly in the row** rather than silently preferring either source — a
   disagreement means one of them is stale, and that is worth knowing.

5. The twelve uncited requirements must all appear with an honest status. Omitting them is the
   failure being fixed.

6. End the document with a summary: counts per status, and an explicit list of every requirement
   that is not `COVERED`.

7. Write `scripts/check-traceability.sh`, failing (exit 1) when:
   - a requirement in `spec.md` is missing from the matrix
   - a row has status `NOT-IMPLEMENTED` with no linked open task
   - a cited test file or implementation path does not exist on disk

   Match the house style of `scripts/check-contract.sh`: documented header, explicit exception
   list, exit 1 for drift and 2 for operational failure, no new dependencies.

8. Wire it into CI if `.github/workflows/ci.yml` exists (prompt `01-ci-pipeline.md`); otherwise
   note in your report that it needs adding when CI lands. Document it in `AGENTS.md` alongside
   the existing merge gates (Constitution VI).
</instructions>

<constraints>
- Do not edit `spec.md`. If a requirement is genuinely untestable as written, record that in the
  matrix and report it — amending a ratified spec is a separate, deliberate act.
- Do not mark anything `COVERED` you have not verified. A matrix that lies is worse than no
  matrix, and is precisely the failure mode this repository already has in `tasks.md`.
- Do not add tests or fix requirements here. This prompt produces the map; the territory is fixed
  by prompts 02–08.
- Do not generate the matrix from `docs/audit/spec-compliance.md` alone. Derive it from the code
  and use the audit as the cross-check — that is what makes the reconciliation in step 4
  meaningful.
</constraints>

<acceptance_criteria>
    bash scripts/check-traceability.sh
    # expected: exit 0

    grep -c '^| FR-' specs/001-solo-ttrpg-assistant/traceability.md
    # expected: 31

    # every FR in the spec appears in the matrix
    grep -oE 'FR-[0-9]{3}' specs/001-solo-ttrpg-assistant/spec.md | sort -u | while read -r fr; do
      grep -q "$fr" specs/001-solo-ttrpg-assistant/traceability.md || echo "MISSING: $fr"; done
    # expected: no output

    make lint && make test
    # expected: unchanged and green

Delete one row, confirm the script fails, restore it. Say in your report that you did this.

Spot-check five rows by opening the cited test and confirming it really proves that requirement.
Name the five in your report.
</acceptance_criteria>

<completion>
Branch `traceability-matrix` off an updated `master`. Commit atomically with short imperative
subjects.

Before finishing, run and report `make lint`, `make test` and `scripts/check-traceability.sh`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass. Do not create or push git remotes.

Report: the status counts, every requirement that is not `COVERED`, every disagreement you found
with the audit, the five rows you spot-checked, and the result of the deleted-row check.
</completion>
