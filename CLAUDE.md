# CLAUDE.md — Lone Wolf

Multi-system solo-TTRPG assistant: admins author *game systems* as a graph of named stages with
per-stage guidance; players run campaigns along that graph, journal per stage, consult weighted
random tables (*oracles*) and roll dice. Monorepo: `backend/` is Symfony 7.4 + API Platform in
hexagonal DDD by bounded context, `frontend/` is Next.js talking to it only through the OpenAPI
contract.

## Governance — read these before changing anything

- `.specify/memory/constitution.md` — six principles that **supersede every other convention**
  here, including this file. Reviewers cite the violated principle number when rejecting work.
- `AGENTS.md` — the delivery rules: task = commit, checkpoint = PR, tests fail before their
  implementation is committed, and the seven merge gates CI enforces on every PR.

Work is specified in `specs/<feature>/` (`spec.md` → `plan.md` → `tasks.md`).

## Where things live

`backend/src/<Context>/{Domain,Application,Infrastructure}` — dependencies point inward only.
Each context has its own `README.md` with its ubiquitous language.

- `Shared/` — cross-context primitives only (the smallest possible shared surface)
- `Rulesets/` — admin-authored game systems and their campaign flows
- `Campaigns/` — campaign lifecycle and the Flow Engine pacing play
- `Journal/` — the play chronicle: append-only entries scoped to stages
- `Oracles/` — weighted random tables admins author, players consult
- `Characters/` — PCs and NPCs conforming to their system's SheetStructure
- `Dice/` — stateless dice mechanics, logged into the Journal
- `Identity/` — accounts, credentials and roles

`frontend/src/app` (routes), `frontend/src/components` (per-context UI),
`frontend/src/lib` (`api/` generated client, `hooks/`, `auth.ts`).

## How to look things up

Prefer `mcp__codegraph__codegraph_explore` over grep-and-read for "where is X" and "how does X
work" — the eight codegraph tools are pre-approved in `.claude/settings.json`, so they cost no
permission prompt and one call usually replaces a whole search loop.

## Commands

- `docker compose exec php composer test:fast` — inner loop: the `unit` suite, no kernel, ~0.5 s.
  Run it constantly; there is no excuse not to.
- `make test` and `make lint` — before any PR (both PHPUnit suites, Behat, Vitest; PHPStan
  level-max + deptrac).
- `scripts/check-contract.sh` — after touching any API resource.
- `make up` / `make down` — boot the stack (backend :8080, frontend :3000).

## Hard rules

- **Never delete, skip or weaken a test to make a suite pass.** Quarantine with an explicit skip
  and explain it in the PR. *A test was once deleted here citing a class that does not exist
  (`ccf09a6`); it was the only cover for a critical defect.*
- **Never mark a `tasks.md` item `[x]` unless every file path it names exists on disk.**
  *`T063` stayed `[X]` for nine days with its feature file deleted; `5002872` put it back.*
- **Never hand-edit `frontend/src/lib/api/schema.gen.ts`.** Regenerate it with
  `frontend/scripts/generate-api-client.sh` — it is the contract, not source.
- **Any work that did not originate in a `tasks.md` task must be converged back into one**,
  or the artifacts stop describing the system.
- **Do not create or push git remotes.** Remote setup is the maintainer's, done manually.
