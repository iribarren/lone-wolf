# 01 · Add the CI pipeline

Wave 0 · no dependencies · branch `ci-pipeline` · ~4 h · fixes audit finding **B1** (critical)

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js talking to it only
through the OpenAPI contract.

Read before changing anything:
- `AGENTS.md` — the delivery rules, including the six merge gates this prompt automates
- `.specify/memory/constitution.md` — the six principles that supersede every other convention
- `Makefile` — the commands that already run every gate locally
- `docs/audit/01-ai-workflow.md` — why this is the highest-leverage change in the repository
</context>

<preconditions>
None. This prompt adds automation and changes no application code.

The build is currently green — all eight gates passed on 2026-08-30. Confirm that before you
start (`make lint && make test`); if something is already red, report it and stop, because the
point of this change is to lock in a passing baseline, not to paper over a failing one.
</preconditions>

<problem>
`.github/` does not exist. There is no CI of any kind.

Meanwhile `AGENTS.md` documents six merge gates under the heading "every PR, no exceptions",
`scripts/check-contract.sh` prints "CI fails on drift", and
`specs/001-solo-ttrpg-assistant/quickstart.md` claims "CI additionally asserts" the SC-004 oracle
distribution. None of that runs anywhere. Every gate is a human remembering to type `make test`.

Why it matters: the audit found five critical defects shipped past a fully green local suite,
and one commit — `ccf09a6` — that deleted a failing Behat test citing
`Laravel\Lux\Bootstrap\Kernel incompatible with Symfony monorepo`. That class does not exist and
this is not a Laravel project. A test was deleted under an invented justification and its task
was marked complete, because nothing independent had to agree. CI is the mechanism that makes
that impossible.
</problem>

<pattern>
Do not invent new commands. Every gate already exists and already passes; reuse the exact
invocations from `Makefile` and `backend/composer.json`:

    composer lint            # = composer analyze (PHPStan level max) + composer layers (deptrac)
    vendor/bin/phpunit --testsuite unit
    vendor/bin/phpunit --testsuite integration
    vendor/bin/behat
    npm run typecheck && npm run lint && npm run test
    scripts/check-contract.sh
    cd frontend && npm run test:e2e

`docker-compose.yml` at the repo root boots the whole stack — php-fpm, nginx, postgres, next —
and postgres has a healthcheck the php service already waits on. Using compose in CI keeps the
pipeline identical to local development, which is the point.
</pattern>

<instructions>
1. Read `AGENTS.md`, the `Makefile`, `docker-compose.yml` and `backend/composer.json` and confirm
   the gate commands are still as described above. If they have changed, use what is actually
   there and note the difference in your report.

2. Create `.github/workflows/ci.yml`, triggered on pull requests and on pushes to `master`.

3. Job `backend` — boot the stack, then run, each as its own named step so a failure is
   immediately legible:
   - `docker compose up -d --build`, waiting for the postgres healthcheck
   - `composer install`
   - `doctrine:migrations:migrate -n` for both the dev and the `test` database
     (the test suite uses a `_test` suffixed database — see `backend/config/packages/doctrine.yaml`)
   - `app:create-admin` and `app:seed:demo` (the integration and Behat suites need seeded content)
   - `composer lint`
   - `vendor/bin/phpunit --testsuite unit`
   - `vendor/bin/phpunit --testsuite integration`
   - `vendor/bin/behat`
   - `scripts/check-contract.sh` (needs `python3` with PyYAML on the runner)

4. Job `frontend` — `npm ci`, then `npm run typecheck`, `npm run lint`, `npm run test`.

5. Job `e2e` — depends on `backend`; boots the stack, seeds it, and runs
   `cd frontend && npm run test:e2e`. Upload Playwright traces as an artifact on failure.

6. Cache Composer and npm downloads.

7. Add `paths-ignore` for `**.md` and `docs/**` on the heavy jobs so documentation changes do not
   burn minutes. Do not add it to a job that would then report success without having run.

8. Update `AGENTS.md` so the "Merge gates" section states that CI enforces them and names the
   workflow file. Constitution VI requires documentation to move in the same change set.
</instructions>

<constraints>
- Enforce exactly the six gates `AGENTS.md` already declares. Do not add new linters, coverage
  thresholds, or style checks — that is a separate decision and a separate PR.
- No gate may be `continue-on-error`. A gate that cannot fail the build is not a gate.
- Change no application code. If a gate fails on `master`, that is a finding to report, not
  something to fix here.
- Do not add a deployment, release, or publish step.
</constraints>

<acceptance_criteria>
- `.github/workflows/ci.yml` exists and is valid YAML (`docker run --rm -i ghcr.io/rhysd/actionlint:latest -` or any equivalent check you have available).
- Every one of the six gates appears as a distinct, named, failure-propagating step.
- Opening a pull request runs the workflow and it passes end to end against the current `master`.
- Deliberately breaking one thing makes CI red, and only that job: e.g. add `$x = 1;` with no
  type declaration to a `src/` file and confirm the PHPStan step fails. **Revert the sabotage
  before finishing** and say in your report that you performed this check.
- `AGENTS.md` names the workflow.
</acceptance_criteria>

<completion>
Branch `ci-pipeline` off an updated `master`. Commit atomically with short imperative subjects;
one logical change per commit (`AGENTS.md`: "Task = commit").

Before finishing, run and report `make lint` and `make test`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, the result of the deliberate-failure check, and
anything you could not verify.
</completion>
