# 2 · The Specifications

*Are they well defined, and could they be used better?*

Short answer: **the specs are well written and under-used.** The quality of `spec.md` is not the
problem — the problem is that the artifacts stop being a living instrument the moment the first
release lands, and that nothing mechanically connects a requirement to the code that satisfies it.

---

## 2.1 What is genuinely good

Judged against how feature specs usually look, this set is above average on several axes that
matter.

**Requirements are testable and unambiguous.** All 31 FRs are RFC-2119 (`MUST`/`MAY`) and pin
concrete behaviour rather than intent. FR-026 names the notation (`NdM±K`) and demands "sensible
bounds"; FR-010 specifies "exactly one entry selected by chance in proportion to configured
likelihoods"; FR-025 says drifted characters are "flagged for review — never hidden, auto-altered,
or silently dropped". You can write a failing test from any of them without asking a question.

**Zero unresolved clarification markers.** No `[NEEDS CLARIFICATION]` survives in `spec.md`,
`tasks.md` or `data-model.md`, and — the part that matters — the decisions that resolved them are
captured in an **Assumptions** section rather than being silently made in code.

**Success criteria are measurable and technology-agnostic**, and the one soft criterion is
honestly labelled. SC-003 ("90 % of stage transitions anticipated") carries its own caveat:
*"Measured manually against a playtest rubric… v1 ships no telemetry."* Marking your own weakest
criterion as unverifiable is a strong signal of good-faith authoring.

**Decisions record their rejected alternatives.** `research.md` holds 11 decisions (R1–R11), each
with Decision / Rationale / Alternatives considered. Six months from now, "why JSONB sheets rather
than EAV" is answerable without archaeology.

**The data model is annotated with the requirement each field serves**, which is the closest thing
in the repo to a traceability artifact.

**Two contracts, not one.** `contracts/openapi.yaml` covers the player API; `contracts/admin-backoffice.md`
separately specifies the server-rendered admin surface — menu routes, per-page field lists,
behavioural guarantees. Recognising that the backoffice needs a contract of its own is a genuinely
mature move that most projects skip.

**Stories are independently shippable.** Each carries *Why this priority* and an *Independent
Test* line, and the PR history (#4–#7, one story each) shows that structure was real, not
decorative.

---

## 2.2 Where they fall down

### 2.2.1 There is no traceability matrix — and it correlates exactly with the failures

Only **19 of 31 FRs** are cited anywhere in `tasks.md`. The uncited twelve: FR-001, 003, 004, 008,
010, 014, 017, 021, 022, 026, 027, 028.

Now compare against [the compliance audit](spec-compliance.md). The requirements that turned out
**not to be met** are FR-003, FR-004 and FR-007; the ones met API-only are FR-021 and FR-023;
partial are FR-001 and FR-017. Of those seven, **five are in the untraced set**. That is not
coincidence — a requirement that no task claims is a requirement nobody checks at the end.

Nothing in the repo answers "which test proves FR-014?" You can reconstruct it by reading, and
the audit did — but that is a research exercise, not a gate.

### 2.2.2 `tasks.md` is treated as write-once, so maintenance has nowhere to land

`tasks.md` reports 89/89 complete. Since then eleven commits of real feature work have shipped —
the admin login form, the campaign-flows editor, the index-crash fix — and **not one task was
added**. The design artifacts were retro-fitted by hand (`ef205e2` appended R11 to `research.md`,
a section to `data-model.md`, a V-section to `quickstart.md`), which shows the right instinct, but
the work itself never entered the task ledger, was never gated, and shipped three critical
regressions.

`speckit-converge` exists precisely to append unbuilt or out-of-band work back into `tasks.md`.
It has never been run.

### 2.2.3 The checklist is a self-assessment

`checklists/requirements.md` is 16/16 passing, with the note *"Validation passed on first
iteration (2026-08-22); no spec updates required."* It was written by the same agent run that
authored the spec it validates. That is marking your own homework: it catches typos and missing
sections, not the judgement errors that matter. `speckit-analyze`, the cross-artifact consistency
skill, has never been run and left no artifact.

### 2.2.4 Task IDs and numbering are unreliable

- `T056`–`T063` were rewritten to `X056`–`X063` by commit `211c3e1`, breaking the `T\d+`
  convention AGENTS.md's commit rule depends on. US3 is now invisible to any grep.
- `X063` is marked complete and names a file that does not exist.
- Thirteen task numbers were never issued (T008, T009, T038, T042, T055, T058, T064, T067, T074,
  T077, T085, T088, T095), so "all complete" cannot be verified by sequence.

Individually cosmetic; together they mean the task ledger cannot be trusted as evidence, which is
the only job it has.

### 2.2.5 The approval gates stop before the risky step

`.specify/workflows/speckit/workflow.yml` defines:

```
specify → [gate: review-spec] → plan → [gate: review-plan] → tasks → implement
```

Two human gates, both on *documents*. `tasks` flows straight into `implement` with nothing in
between — no review of the task breakdown, and no gate at the point where an agent starts writing
code and marking its own work complete. Given the `ccf09a6` incident, that is the gate that is
missing.

### 2.2.6 The contract gate is narrower than the constitution implies

`check-contract.sh` documents its own exclusions honestly (`SKIP_PATHS = {/auth/login}`, four
skipped error schemas). The deeper issue is what it *cannot* see:

- Gate A compares path and method sets.
- Gate B compares **schema declarations** by property-subset against *any* runtime schema.
- Neither ever fetches a response body.

So a schema can be declared perfectly and serialised wrongly, and the gate stays green. That is
literally defect A5: `POST /campaigns/{id}/rolls` declares embedded objects and emits IRI strings.
Constitution V says "the API MUST match the contract"; the gate only checks that the API *claims*
to.

Related: `/auth/login` never reaches the OpenAPI document at all (it is firewall-intercepted), so
the login request and response are the one part of the integration surface with **no contract and
no types** — `AuthGate` reaches it through an `apiPath()` cast.

### 2.2.7 A constitutional tension nobody resolved

Principle V prohibits "session sharing" between frontend and backend. The `/admin/login` firewall
added in `32e18b7` is session-based. `docs/architecture.md` and `contracts/admin-backoffice.md`
scope it as an admin surface internal to the backend — a defensible reading, and probably the
right one. But the constitution's amendment procedure (written rationale, principle diff,
migration plan, ratification) exists for exactly this, and was not used. The document still reads
absolutely while the code does something else, which quietly teaches everyone that the
constitution is negotiable in practice.

---

## 2.3 Recommendations

### R1 — Generate and gate a traceability matrix

`specs/001-solo-ttrpg-assistant/traceability.md`, one row per FR: requirement → task ID(s) →
test(s) → implementing file(s) → status. Generate it from the artifacts and the code, review it by
hand, and make an uncovered FR a CI failure. This is the single change that would have surfaced
FR-003, FR-004, FR-007 and FR-021 before release rather than during an audit.

### R2 — Make `tasks.md` a ledger, not a monument

New rule: **no code without a task.** Anything not already in `tasks.md` gets appended before the
work starts — via `/speckit-converge` for discovered work, or a new `specs/00N-<slug>/` for
anything with its own user-facing behaviour. The flow-editor increment was a real feature with
its own research entry, its own contract section and its own UI; treating it as unlogged
maintenance is what left it untested.

### R3 — Add a gate between `tasks` and `implement`

Run `/speckit-analyze` after `/speckit-tasks` as a hard gate, and add a human checkpoint on the
task breakdown. Cheap, and it is the gate positioned where the failures actually happen.

### R4 — Have the checklist validated by something other than its author

Either run `speckit-checklist` in a separate session with no memory of authoring the spec, or add
a "reviewer" column recording who or what independently confirmed each item. A 16/16 written by
the author is not evidence.

### R5 — Strengthen the contract gate to inspect payloads

Extend `check-contract.sh` with a Gate C: hit a handful of seeded endpoints with a real token and
diff the actual JSON against the contract schema (required properties present, no `$ref`
collapsed to a string). Anchor Gate B to schema *names* rather than "any runtime schema with a
superset of these properties". And bring `/auth/login` into the contract properly — either as a
documented custom operation or via a hand-maintained typed wrapper — so the login surface stops
being the one untyped hole in a contract-first system.

### R6 — File the Principle V amendment

Bump the constitution to 1.1.0 with an explicit clause: Principle V governs the **player-facing
API**; the EasyAdmin backoffice is a server-rendered surface internal to the backend and may use
session authentication. Rationale, diff and ratification per the existing governance section. The
behaviour does not change; the document stops being wrong.

### R7 — Repair the task ledger

Restore `X056`–`X063` to `T056`–`T063`. Either create the missing `authoring.feature` or reopen
`T063`. Add a note documenting the thirteen never-issued numbers so nobody re-audits them. Then
enforce the format with the check from [01-ai-workflow.md](01-ai-workflow.md) R4.

---

## 2.4 Prompts

Extracted to [`docs/prompts/`](../prompts/README.md) as standalone briefs — each is runnable in a
fresh Claude Code session with no prior context, via its slash command or by pasting the file.

| Prompt | Covers |
|---|---|
| [`/traceability-matrix`](../prompts/12-traceability-matrix.md) | R1 — generate and gate the FR → task → test → code matrix |
| [`/converge-increment`](../prompts/13-converge-increment.md) | R2 — make `tasks.md` a ledger rather than a monument |
| [`/task-integrity`](../prompts/10-task-integrity.md) | R7 — repair the corrupted ids and enforce the format |
| [`/constitution-amendment`](../prompts/14-constitution-amendment.md) | R6 — file the Principle V amendment |
| [`/contract-gate-payloads`](../prompts/15-contract-gate-payloads.md) | R5 — make the contract gate inspect payloads |

R3 (a gate between `tasks` and `implement`) and R4 (independent checklist validation) are process
changes rather than units of work, and are folded into `/converge-increment` and
`/claude-md` respectively.
