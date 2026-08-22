# AGENTS.md — Lone Wolf

Guidance for AI agents and contributors working in this repository.

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
   (run the drift check once it exists)
6. Documentation updated in the same change set (Constitution VI)

A PR failing any gate must not be merged. Reviewers cite the violated
Constitution principle number when rejecting work.

### Remotes

Remote/repository setup is handled manually by the maintainer before the first
implementation PR; agents must never create or push remotes unless explicitly
asked.
