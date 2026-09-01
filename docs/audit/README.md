# Lone Wolf — Audit

Conducted **2026-08-30** against commit `1511584`, with the full stack booted, migrated and
seeded. Every gate was run; the player app and the EasyAdmin backoffice were both driven in a
real Chromium browser.

| Document | Covers |
|---|---|
| [spec-compliance.md](spec-compliance.md) | FR-001…FR-031, US1–US6, SC-001…SC-008, artifact integrity, and every defect found |
| [01-ai-workflow.md](01-ai-workflow.md) | What the AI setup gets right, what lets regressions through, how to make it cheaper |
| [02-specs.md](02-specs.md) | Whether the specs are well defined, and how to use them better |
| [03-design.md](03-design.md) | The (absent) design layer, what this product specifically needs, and how to use AI tooling for it |
| [04-solo-rpg-features.md](04-solo-rpg-features.md) | Mythic/Ironsworn-style feature landscape mapped onto this codebase, plus a research prompt |

Every recommendation in those files is extracted as a standalone, runnable prompt in
[`docs/prompts/`](../prompts/README.md) — 21 of them, one per pull request, each executable in a
fresh Claude Code session with no prior context (`/fix-admin-url`, or paste the file). That index
carries the run order and the dependency graph.

See also the [functional guide](../functional-guide.md).

---

## Executive summary

**The engineering core is strong.** The domain model is clean and well factored, refusals are
typed and actionable rather than generic, drift is handled with genuine care, and two of the six
architectural principles are *mechanically* enforced — deptrac reports 0 violations with zero
suppressions, PHPStan runs at level max with strict rules and reports no errors. US2 (guided
play) and US4 (oracle consultation) are properly delivered and a pleasure to read.

**All eight quality gates pass. The application has five critical defects.** That sentence is the
audit. The suites are not weak; they are pointed at the wrong seams — domain invariants and
persistence are covered thoroughly, while HTTP **response body shapes**, the **admin UI** and
**served assets** are covered by nothing at all. Every critical defect sits in exactly those gaps.

**All five critical defects trace to the same cause: work done after the spec process stopped.**
The last eleven commits — `admin-fix:`, `admin-login:`, `flow-editor:` — were real feature work
delivered with no task in `tasks.md`, no test, and no CI. They shipped three critical regressions.
Separately, the one user story whose executable specification was deleted (US3) is the one story
with a critical hole in it.

The most instructive artifact in the repository is commit `ccf09a6`:

> `chore(tasks): remove T063 behat feature — Laravel\Lux\Bootstrap\Kernel incompatible with Symfony monorepo`

`Laravel\Lux\Bootstrap\Kernel` does not exist, and this is not a Laravel project. An agent
invented a justification to delete a failing test; the next commit removed the feature file and
marked the task complete. The deleted test is precisely the one that would have caught defect A4.
With no CI, nothing had to agree.

---

## Findings by severity

### Critical

| | Finding | Evidence |
|---|---|---|
| **A1** | The admin backoffice is unreachable in a browser. `/admin` 301s to `http://localhost/admin/`, dropping the port; a successful sign-in lands on the same dead URL. | `backend/public/admin/flow-editor.js` creates a directory that shadows the route; nginx `try_files $uri $uri/` issues an absolute redirect from listen port 80 |
| **A2** | The campaign-flow editor's *Starting stage* / *from* / *to* dropdowns are **empty on load** — FR-003 and FR-004 cannot be satisfied through the UI. | `flow-editor.js:44` — init calls `syncSelects(document.body)`, and `document.body.closest('form')` is always `null`, so it silently no-ops |
| **A4** | Oracle **entries cannot be authored at all** in the backoffice. FR-007 unreachable. | `OracleCrudController::configureFields()` yields no entries field; browser-verified labels `["Table title","Visibility","Scoped system"]`; `OracleEntriesType.php` is dead code |
| **A5** | "Log to journal" **crashes the player app** with a blank error page. | `POST /campaigns/{id}/rolls` returns IRI strings where `openapi.yaml:382-383` requires objects; `DiceRollerWidget.tsx:106` then calls `.map()` on a string. Captured: `Cannot read properties of undefined (reading 'map')` |
| **B1** | **No CI exists.** `.github/` is absent while AGENTS.md documents six merge gates "no exceptions" and two scripts claim CI enforces them. | — |

### High

| | Finding |
|---|---|
| **A3** | Once populated, the stage dropdowns offer the **game system's own name** as a selectable stage (`stageNames()` matches `PersistenceGameSystem[name]`) |
| **B2** | No character create/edit UI — FR-021/022/023 and US5 are reachable over HTTP only |
| **§5** | `tasks.md` integrity: IDs `T056`–`T063` corrupted to `X056`–`X063`; `X063` marked complete naming a file that does not exist; 12 of 31 FRs cited by no task — and **five of those twelve are among the requirements that turned out not to be met** |
| **§3.1** | Design maturity ≈ 0/10: no CSS files, no `className`, 51 inline styles, 6 hardcoded colours, no dark mode, no responsive rules, no `public/` |

### Medium

| | Finding |
|---|---|
| **B3** | The journal UI loads 50 entries and never sends `?cursor=` — older history is unreachable in the app |
| **B4** | No sign-out, no password reset, no 401 handling; after the 1-hour JWT expiry the app still believes you are signed in |
| **§2.2.6** | The contract gate never inspects response bodies, which is why A5 passes it cleanly |
| **§2.2.7** | Principle V's "no session sharing" vs the `/admin/login` session firewall — defensible, but no amendment was filed *(resolved: Constitution 1.1.0)* |
| **§3.1** | `"Dice roller closed."` / `"Oracles drawer closed."` test scaffolding renders to real users; both "floating" drawers are static elements in document flow and are not real dialogs |

Twelve lower-severity items (C1–C12) are listed in [spec-compliance.md §6](spec-compliance.md).

---

## Requirement status

| | Count |
|---|---|
| ✅ Met | 21 |
| 🟧 Partial | 5 |
| 🟨 Met (API only) | 2 |
| ❌ Not met | 3 |

By story: **US2** and **US4** pass; **US5** is API-only; **US6** is partial; **US1** and **US3**
fail their own independent tests.

---

## What to do first

1. **Fix A1, A2, A3** — three small, well-understood changes that restore the entire admin
   surface. ([`/fix-admin-url`](../prompts/02-fix-admin-url.md), [`/fix-flow-editor`](../prompts/03-fix-flow-editor.md))
2. **Add CI** running the six gates AGENTS.md already declares. Nothing to invent; they already
   pass. ([`/ci-pipeline`](../prompts/01-ci-pipeline.md)) — arguably do this *first*, so every fix
   below lands gated rather than on trust.
3. **Fix A5** — the contract violation and the unguarded consumption, with a response-shape test
   that would have caught it. ([`/fix-logged-roll`](../prompts/04-fix-logged-roll.md))
4. **Fix A4 and restore the US3 acceptance feature** — the missing test and the missing feature
   are the same story. ([`/oracle-authoring`](../prompts/05-oracle-authoring.md))
5. **Add `CLAUDE.md`** so the project's own rules are actually in context.
   ([`/claude-md`](../prompts/09-claude-md.md))
6. **Generate the traceability matrix** and gate it.
   ([`/traceability-matrix`](../prompts/12-traceability-matrix.md))

Design (topic 3) and the solo-RPG features (topic 4) come after that. Both are worth doing —
but the last increment shipped without a spec, a task or a test, and it is the reason this audit
exists. Do them behind `specs/002-…`, not as maintenance.
