# 09 · Write CLAUDE.md

Wave 5 · no dependencies · branch `claude-md` · ~1 h

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js talking to it only
through the OpenAPI contract.

Read before writing anything:
- `AGENTS.md` — the project's real delivery rules
- `.specify/memory/constitution.md` — the six principles that supersede every other convention
- `docs/audit/01-ai-workflow.md` — the analysis this prompt comes from
- `.claude/settings.json` and `.mcp.json` — the tooling already configured
</context>

<preconditions>
None. This prompt writes documentation and changes no code.
</preconditions>

<problem>
**There is no `CLAUDE.md` in this repository**, so the project's rules are never in context.

Claude Code auto-loads `CLAUDE.md`. It does not auto-load `AGENTS.md`. Every rule that actually
governs this project — "task = commit", "checkpoint = PR", "tests must fail before their
implementation is committed", the six merge gates — lives in `AGENTS.md` and therefore reaches an
agent only if a human remembers to paste it.

The audit found that this is not hypothetical. Across ~110 spec-driven commits the discipline
held perfectly and zero critical defects shipped. Across the 11 commits after it lapsed, three
critical regressions shipped, no tasks were logged, and one commit deleted a failing Behat test
citing `Laravel\Lux\Bootstrap\Kernel incompatible with Symfony monorepo` — a class that does not
exist, in a project that is not Laravel. The discipline was being held by the operator's memory,
not by the harness.
</problem>

<instructions>
1. Read `AGENTS.md` and `.specify/memory/constitution.md` in full. `CLAUDE.md` must not
   contradict or duplicate them — it points at them and repeats only what an agent most needs at
   the moment of decision.

2. Write `CLAUDE.md` at the repository root. Keep it under roughly 60 lines; a file nobody
   finishes reading is the same as no file. Cover:

   - **What this is**, in two sentences, and the monorepo split.
   - **Governance.** `.specify/memory/constitution.md` holds six principles that supersede
     everything; `AGENTS.md` holds the delivery rules. Say that reviewers cite the violated
     principle number.
   - **Where things live.** The eight bounded contexts under `backend/src` — Shared, Rulesets,
     Campaigns, Journal, Oracles, Characters, Dice, Identity — one line each, plus
     `frontend/src/{app,components,lib}`. Derive these from the code, not from this prompt.
   - **How to look things up.** Prefer `mcp__codegraph__codegraph_explore` over grep-and-read for
     "where is X" and "how does X work"; the eight codegraph tools are already pre-approved in
     `.claude/settings.json`, so they cost no permission prompt.
   - **Commands.** The fast inner loop, `make test` and `make lint` before any PR, and
     `scripts/check-contract.sh` after touching any API resource.
   - **Hard rules**, phrased as rules, each with its one-line reason:
     * Never delete, skip or weaken a test to make a suite pass. Quarantine with an explicit skip
       and an explanation in the PR. *(This has happened here — commit `ccf09a6`.)*
     * Never mark a `tasks.md` item `[x]` unless every file path it names exists on disk.
       *(This has happened here — task `X063`.)*
     * Never hand-edit `frontend/src/lib/api/schema.gen.ts`; regenerate it with
       `frontend/scripts/generate-api-client.sh`.
     * Any work that did not originate in a `tasks.md` task must be converged back into one.
     * Do not create or push git remotes.

3. Add a `composer test:fast` script to `backend/composer.json` running only the `unit` suite, and
   reference it in `CLAUDE.md` as the inner loop. The unit suite boots no kernel and completes in
   well under a second across 117 tests, so there is no excuse for an agent not to run it — but
   only if it is a named command.

4. Cross-check every claim you write. If you state a path, a command or a context name, verify it
   exists first. A `CLAUDE.md` that misdirects is worse than none.
</instructions>

<constraints>
- Do not restate the constitution's principles in full. Point at the file.
- Do not add rules that are not already project policy. If you think a new rule is needed, propose
  it in your report; `AGENTS.md` and the constitution are the places rules are ratified, and the
  constitution has an amendment procedure.
- Do not add hooks, agents, or slash commands here. Tool-chain changes are prompt
  `11-toolchain-hygiene.md`.
- Do not modify `AGENTS.md` beyond, at most, a one-line cross-reference to `CLAUDE.md`.
</constraints>

<acceptance_criteria>
- `CLAUDE.md` exists at the repository root, under ~60 lines.
- Every path, command and context name in it resolves. Verify mechanically — for each backticked
  path, check it exists; for each command, check it runs.
- `composer test:fast` runs the unit suite and passes.
- A reader who knows nothing about the project can answer, from `CLAUDE.md` alone: what is this,
  what are the rules, where does code live, how do I look things up, how do I check my work, and
  what must I never do.
- It does not contradict `AGENTS.md` or the constitution on any point.
</acceptance_criteria>

<completion>
Branch `claude-md` off an updated `master`. Commit atomically with short imperative subjects.

Before finishing, run and report `make lint` and `make test` — this change should not affect
either, and confirming that is the point.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass. Do not create or push git remotes.

Report: what you wrote, which claims you verified mechanically, and any rule you considered adding
but left out because it is not ratified policy.
</completion>
