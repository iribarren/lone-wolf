# AGENTS.md — Lone Wolf

Guidance for AI agents and contributors working in this repository.

The short version Claude Code auto-loads is `CLAUDE.md` at the repository root; this
file remains the authority on delivery rules.

## Project

Solo TTRPG digital assistant ("Lone Wolf"). Monorepo: `/backend` (PHP 8.3,
Symfony LTS, hexagonal DDD by bounded context), `/frontend` (Next.js/React).
Governance lives in `.specify/memory/constitution.md` — its six principles
supersede every other convention in this file and in code review.

## Delivery & PR Strategy

Work is specified in `specs/<feature-folder>/` (`spec.md` → `plan.md` →
`tasks.md`). The following rules apply to every feature regardless of scope:

### Task execution

- Execute tasks from the feature's `tasks.md` **one by one**, in ID order
  within each phase.
- **Task = commit**: each completed task gets an atomic commit prefixed with
  its task ID (e.g. `T003: add backend Dockerfile`). Never batch unrelated
  tasks into one commit.
- Tests are written first within a story phase and must fail before their
  implementation is committed (Constitution IV).

### Pull requests

- **Checkpoint = PR**: open a pull request when a phase/story checkpoint is
  reached. Never mix user stories in one PR.
- **Branch per story**: create `<feature>-us<N>-<slug>` branches off updated
  `main` (e.g. `001-us2-guided-campaign`); the foundation/setup PR uses
  `<feature>-foundation`.
- **Sequential merging**: merge PR N before starting N+1. Do not maintain
  stacked branches. `[P]` parallel markers buy parallelism within a working
  session, not across PRs.
- A PR description states which tasks/checkpoint it closes and how the
  independent test criterion from the story was verified.

### Merge gates (every PR, no exceptions)

1. PHPUnit `unit` suite green (Domain/Application tests boot no kernel)
2. PHPUnit `integration` suite green
3. Behat features for touched stories green (ubiquitous-language specs)
4. PHPStan level-max + deptrac layer rules clean (`composer lint`)
5. API matches the feature's contract in `specs/<feature>/contracts/`
   (`scripts/check-contract.sh`)
6. Documentation updated in the same change set (Constitution VI)
7. `specs/*/tasks.md` intact (`scripts/check-task-integrity.sh`)
8. Every ratified requirement traced (`scripts/check-traceability.sh`)

**CI enforces all eight.** `.github/workflows/ci.yml` runs them on every pull
request and on every push to `master`, each gate as its own named step, none
of them `continue-on-error`. It boots the stack from this repository's
`docker-compose.yml` and runs the same commands the `Makefile` runs locally,
plus the frontend checks (`npm run typecheck`/`lint`/`test`) and the Playwright
quickstart happy path. Run `make lint && make test` before pushing to get the
same answer sooner.

Gate 7 keeps the task ledger usable as evidence. It rejects a task id that is
not `T000`-shaped, the same id issued twice, and — the one that matters — a
task marked `[x]` citing a file that is not on disk. All three have happened:
`211c3e1` renamed `T056`-`T063` to `X056`-`X063` while flipping them complete,
hiding a whole user story from the id greps this document's commit rule
depends on, and deleted the feature file `T063` claimed. The script needs
nothing but bash, so run it as often as you like. Intentional exceptions live
in its header with a rationale each; add one only when an artifact was
superseded by a later task, never to quiet a task that was not delivered.

Gate 8 keeps the requirements honest. `specs/<feature>/traceability.md` carries
one row per functional requirement — story, citing task, proving test,
implementing file, status — and the gate rejects a ratified requirement with no
row, a `NOT-IMPLEMENTED` row with no open task behind it, and a cited test or
implementation path that is not on disk. It exists because only 19 of this
feature's 31 requirements were cited by any task, and five of the seven the
compliance audit found undelivered sat in the untraced set: untraced and
undelivered correlate almost exactly. Like gate 7 it needs nothing but bash.
Statuses are `COVERED`, `NO-TEST`, `NO-TASK` and `NOT-IMPLEMENTED`; record the
one you verified, never the one you would prefer — a matrix that lies is worse
than no matrix.

Gate 6 is checked by path: a PR touching application code must also touch a
document. That proves the question was asked, not that the answer was any
good — judging the answer stays with the reviewer. Tests do not count as
code, and a change with genuinely nothing to document is waived with the
`docs:none` label, which the job log records and the PR shows. Satisfying
gate 6 with a cosmetic documentation edit is itself a Constitution VI breach.

A PR failing any gate must not be merged; a red pipeline is not a reason to
weaken, skip or delete the failing check. Reviewers cite the violated
Constitution principle number when rejecting work.

### Remotes

Remote/repository setup is handled manually by the maintainer before the first
implementation PR; agents must never create or push remotes unless explicitly
asked.

## Agent integration

**Claude Code is the canonical — and only — agent integration.** Spec Kit records this in
`.specify/init-options.json` (`"ai": "claude"`) and `.specify/integration.json`
(`installed_integrations: ["claude"]`, invoke separator `-`).

- The spec commands live in `.claude/skills/speckit-*/` — ten skills, invoked as
  `/speckit-specify`, `/speckit-plan`, `/speckit-tasks`, `/speckit-implement`, plus `analyze`,
  `checklist`, `clarify`, `constitution`, `converge` and `taskstoissues`. They are tracked, so
  the command set that actually runs is the one reviewers can read.
- `.specify/integrations/*.manifest.json` are SHA-256 integrity records of the files Spec Kit
  installed. A file that drifts from its recorded hash is either reverted or re-recorded with a
  one-line reason — an unexplained mismatch is a review failure.

**Changing the integration means re-running Spec Kit's init, never hand-editing.** Adding or
switching a front-end regenerates the command set and its manifest together; editing the
installed commands or the manifest by hand desynchronises them silently. The opencode
integration was removed this way (it had been superseded by claude but left tracked, giving two
command sets that would drift independently); if it or another agent is ever added back, install
it through Spec Kit and note here that both sets are maintained deliberately.
