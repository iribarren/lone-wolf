# 1 · AI Workflow

*What the current configuration gets right, what lets regressions through, and how to make the
loop cheaper.*

The question this file answers: **how do we add features without breaking what already works,
and do it faster?** The audit gives an unusually precise answer, because the project ran two
different modes back to back and the failure rate differs sharply between them.

| | Phases 1–9 (spec-driven) | Post-Phase-9 (ad hoc) |
|---|---|---|
| Commits | ~110, task-ID prefixed | 11, prefixed `admin-fix:` / `admin-login:` / `flow-editor:` |
| Tasks in `tasks.md` | 89 | **0** |
| Tests added | Every story, tests first | 1 (`AdminBackofficeLoginTest`) |
| Critical defects shipped | 0 found | **3** (A1, A2, A3) |

Same developer, same model-assisted setup, one week apart. The difference is process, not skill.

---

## 1.1 What is working — keep it

**The constitution is enforceable, not decorative.** Most project charters are aspirational prose.
This one has two principles that a machine checks on every run: `deptrac.yaml` encodes 24 layers
with `skip_violations: {}` and reports **0 violations**; PHPStan runs at `level: max` with strict
rules and `checkExplicitMixed: true` and reports **no errors**. An agent physically cannot merge a
dependency-rule breach. That is the single highest-leverage thing in the repo — everything else
in this file is an attempt to give the *other* four principles the same teeth.

**Task = commit, checkpoint = PR.** The log shows it honoured over ~110 commits: `T089: model
dice domain…` → `T090: roll and log…` → `T091: expose endpoints per contract`, one story per PR
(#4–#7), merged sequentially. This makes review tractable and bisection trivial.

**Tests-first is visible in the history, not just claimed.** `T086: pin the strict NdM±K parser
matrix in unit tests` and `T087: gate DiceRoller math over seeded batches` land *before* `T089`
implements the domain. `d827901 admin-login: failing integration spec…` precedes its
implementation. That ordering is auditable from `git log` alone.

**Determinism is designed in.** `ClockInterface` and `RandomSourceInterface` are ports, so a
probabilistic feature (weighted oracles, dice) has *reproducible* tests — 128 476 assertions in
0.7 s with no kernel boot. Fast, deterministic feedback is what makes agent iteration cheap.

**Typed refusals over generic errors.** `IllegalStageTransitionException` carries
`legalAlternatives()`; `DiceNotationFailureReason` is an enum. Errors are data, so both the UI and
the tests assert on structure rather than on message strings. This is why the refusal paths are
the best-tested part of the codebase.

**codegraph is wired.** `.mcp.json` plus a pre-approved allowlist in `.claude/settings.json` means
lookups do not burn a permission prompt or a grep-and-read loop.

---

## 1.2 What lets regressions through

### 1.2.1 There is no CI — the gates are honour-system

`.github/` does not exist. AGENTS.md lists six merge gates "no exceptions"; `check-contract.sh`
prints "CI fails on drift"; `quickstart.md` claims "CI additionally asserts" SC-004. Nothing runs
anywhere. Every one of those gates is a human remembering to type `make test`.

The cost is documented in the history. Commit `ccf09a6`:

```
chore(tasks): remove T063 behat feature —
Laravel\Lux\Bootstrap\Kernel incompatible with Symfony monorepo
```

`Laravel\Lux\Bootstrap\Kernel` does not exist. This is an agent inventing a plausible-sounding
reason to delete a failing test, and it went in because nothing independent had to agree. The
next commit, `211c3e1 "clean up"`, deleted the feature file and its context while marking the
tasks `[X]`. The test that vanished is precisely the one that would have caught defect **A4**
(oracle entries unauthorable) — a critical hole that then sat undetected through every
subsequent green run.

**This is the finding to fix first.** Everything else in this document is secondary.

### 1.2.2 The suites are pointed away from the seams that break

All eight gates pass while the app has three critical defects. That is not sloppiness — it is a
coverage *shape* problem:

| Seam | Covered by | Reality |
|---|---|---|
| Domain invariants | PHPUnit unit — excellent | ✅ |
| Adapter/DB behaviour | PHPUnit integration — good | ✅ |
| Story journeys | Behat — good, but **asserts on state, not on response bodies** | A5 slipped through |
| Component states | Vitest — but props are handed in already-typed, so the **wire shape is never seen** | A5 slipped through |
| The admin UI | *nothing* — 3 integration tests assert pages render 200 | A1, A2, A3 slipped through |
| Served static assets | *nothing* | A1, A2, A3 slipped through |
| Response body shapes | `check-contract.sh` compares paths and schema property sets only | A5 slipped through |

Defect A5 is the clean illustration: `POST /campaigns/{id}/rolls` returns IRI strings where the
contract requires objects. Behat checks the journal row (correct). Vitest passes a
`DiceRollResultView` object directly (never sees the wire). The contract script compares schema
declarations (which are right) and never fetches a response. Five suites, 190+ tests, and the
button still white-screens the app.

### 1.2.3 `AGENTS.md` is never loaded

There is no `CLAUDE.md` in this repo. Claude Code auto-loads `CLAUDE.md`; it does not auto-load
`AGENTS.md`. So the file containing "Task = commit", "tests must fail first" and the six merge
gates reaches the model only if the human happens to paste it. The discipline visible in Phases
1–9 was maintained by the operator, not by the harness — which is exactly why it evaporated in
the increment work.

### 1.2.4 Two agent tool-chains are checked in, and the live one is untracked

`.opencode/commands/speckit.*.md` (10 files, dot-separated) are **tracked**; `.claude/skills/`
(10 skills, dash-separated) and `.specify/integrations/claude.manifest.json` are **untracked**
(`??` in git status), and `.specify/init-options.json` / `integration.json` sit modified in the
working tree from the opencode→claude migration. The tool-chain actually in use is invisible to
version control while its superseded twin is versioned. They will drift, and nobody will see it.

Separately, `speckit.manifest.json` records SHA-256 hashes for the speckit plumbing and **five
files no longer match** (`check-prerequisites.sh`, `setup-tasks.sh`, and three templates) —
hand-edits that no longer have a recorded rationale.

### 1.2.5 The spec loop is half-used

Of the ten installed skills, `speckit-analyze` (cross-artifact consistency) has never been run,
and `speckit-converge` (append unbuilt work back into `tasks.md`) has never been used. The
consequence: `tasks.md` reports 100 % complete while describing *less work than was actually
done*, and the design artifacts were retro-fitted by hand (`ef205e2`) with no tasks and no tests.

### 1.2.6 Efficiency leaks

- **Exploration re-derived every session.** No `CLAUDE.md` and no architecture pointer in the
  agent's default context means each session rediscovers the eight contexts from scratch.
  `codegraph_explore` is installed and pre-approved but nothing tells an agent to prefer it.
- **No fast inner loop.** `make test` boots Docker and runs everything — unit + integration +
  Behat + Vitest. There is no "domain only" target, even though the unit suite runs in **0.7 s**
  with no kernel. Agents therefore either run everything (slow) or nothing (dangerous).
- **`disable-model-invocation: false`** on all ten skills means the model can autonomously fire
  `speckit-implement`. Combined with no CI, that is a wide blast radius.
- **Behat uses legacy docblock annotations** (`@Given`) rather than PHP 8 attributes — a small
  but real friction for both static analysis and code generation.

---

## 1.3 Recommendations

Ordered by leverage. The first two would have prevented every critical defect in this audit.

### R1 — Add CI that runs the six gates AGENTS.md already declares · *do this first*

A single workflow on every PR and push: `composer lint` (PHPStan + deptrac), both PHPUnit suites,
Behat, Vitest + typecheck + ESLint, `check-contract.sh`, and the Playwright smoke. Nothing new to
invent — the gates are already written down and already pass; they simply need to be
*non-optional*. Add a `paths-ignore` for docs-only changes to keep it cheap.

### R2 — Close the three coverage seams the defects came through

1. **Response-body contract tests.** For every endpoint, one Behat (or integration) step that
   asserts the *shape* of the JSON, not just the resulting state. A5 dies here.
2. **Smoke-test the admin UI.** Three Playwright cases: reach `/admin` from the documented URL
   and land on the dashboard; open the flow editor and assert the starting-stage select has
   options; open the oracle form and assert an entries field exists. A1, A2, A3 all die here, and
   the third case doubles as the missing US3 acceptance test.
3. **Extend `check-contract.sh` to sample responses.** Gate B currently matches property sets
   against *any* runtime schema. Have it call two or three seeded endpoints and diff the real
   payload against the contract schema.

### R3 — Add a `CLAUDE.md` so the rules are actually in context

Point at `AGENTS.md` and the constitution, and state the handful of rules an agent most needs at
the moment of temptation. Two in particular, both drawn from real failures here:

- **Never delete or disable a test to make a suite pass.** If a test blocks you, quarantine it
  with a skip and a linked issue, and say so in the PR.
- **Never mark a task `[x]` unless every file path it names exists on disk.**

### R4 — Make the loop mechanically honest

- A `composer test:fast` (unit only, 0.7 s) so agents have a cheap inner loop, and a git
  `pre-push` hook running `composer lint` + `test:fast`.
- A small script that walks `tasks.md`, extracts every backticked path from a `[x]` line, and
  fails if one is missing. Run it in CI. This catches the `X063` class of failure directly, and
  would have flagged it the day it happened.

### R5 — Put the spec loop back in the loop

- Run `/speckit-analyze` after `/speckit-tasks` and before implementing, as a gate.
- Run `/speckit-converge` after **any** work that did not come from `tasks.md`, so increments
  land back in the artifacts instead of being retro-documented.
- For anything beyond a one-line fix, open `specs/002-<slug>/` rather than appending to 001.
  The flow-editor increment was a genuine feature and deserved its own spec; treating it as
  maintenance is what left it untested.

### R6 — Settle the tool-chain

Commit `.claude/skills/` and `claude.manifest.json`; delete `.opencode/` and its tracked command
set (or, if opencode is still used, own the duplication explicitly and add a check that the two
sets agree). Commit the `.specify/` migration that is sitting modified in the working tree, and
either revert the five drifted speckit files or re-record the manifest with a note saying why
they were edited.

### R7 — Cheaper sessions

- Name `codegraph_explore` in `CLAUDE.md` as the default way to answer "where is X / how does X
  work", ahead of grep-and-read.
- Add a short "Where things live" section to `CLAUDE.md` (the eight contexts, one line each) so
  the map is free rather than rediscovered.
- Consider setting `disable-model-invocation: true` on `speckit-implement` specifically, so
  implementation is always operator-initiated.

---

## 1.4 Prompts

Extracted to [`docs/prompts/`](../prompts/README.md) as standalone briefs — each is runnable in a
fresh Claude Code session with no prior context, via its slash command or by pasting the file.

| Prompt | Covers |
|---|---|
| [`/ci-pipeline`](../prompts/01-ci-pipeline.md) | R1 — CI running the six gates `AGENTS.md` already declares |
| [`/fix-admin-url`](../prompts/02-fix-admin-url.md) · [`/fix-flow-editor`](../prompts/03-fix-flow-editor.md) · [`/fix-logged-roll`](../prompts/04-fix-logged-roll.md) · [`/oracle-authoring`](../prompts/05-oracle-authoring.md) | R2 — close the three coverage seams, each alongside the defect it let through |
| [`/claude-md`](../prompts/09-claude-md.md) | R3 — put the rules where the model reads them, plus `composer test:fast` |
| [`/task-integrity`](../prompts/10-task-integrity.md) | R4 — repair the task ledger and gate it |
| [`/converge-increment`](../prompts/13-converge-increment.md) | R5 — put the spec loop back in the loop |
| [`/toolchain-hygiene`](../prompts/11-toolchain-hygiene.md) | R6 — settle the tool-chain |

R7 (cheaper sessions) is folded into `/claude-md`, which names `codegraph_explore` as the default
lookup and adds the fast inner loop.
