# 20 · Add the visual and accessibility safety net

Wave 6 · after `19-ui-primitives` · branch `visual-regression` · ~half a day

<context>
Lone Wolf is a multi-system solo-TTRPG assistant; the player app is Next.js with Playwright
already configured (`frontend/playwright.config.ts`: chromium, `fullyParallel: false`,
`retries: 0`, 60s timeout, `trace: 'retain-on-failure'`, auto-starts `npm run dev` unless
`E2E_BASE_URL` is set) and one existing E2E spec, `frontend/tests/e2e/play.spec.ts`.

Prompts 18 and 19 have introduced a token layer, dark mode and a set of UI primitives. This prompt
locks that in so the next restyle cannot silently regress it.

Read before changing anything:
- `docs/audit/03-design.md` §3.3 Phase 4 — the plan this implements
- `frontend/playwright.config.ts` and `frontend/tests/e2e/play.spec.ts` — the conventions to follow
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
</context>

<preconditions>
`18-design-tokens.md` and `19-ui-primitives.md` must have landed — there is no point baselining
screenshots of a design that is about to change wholesale.

    docker compose up -d --build
    docker compose exec php bin/console app:seed:demo

Confirm the whole frontend suite is green before you start; you are about to freeze its output.
</preconditions>

<problem>
**Nothing protects the design or the accessibility semantics from the next change.**

The app's accessibility is its strongest quality — consistent `aria-label` on every region,
`role="alert"` on all eight error surfaces, `role="status"` on confirmations, `aria-busy` on
loading states, real labels on every input. It survived the token migration and the primitives
work only because those prompts said so explicitly, and because the E2E suite selects by role and
accessible name and would have broken loudly.

That is a convention, not a gate. There is no accessibility assertion anywhere, and no visual
baseline at all — a change that quietly drops an `aria-label`, or renders dark-mode text on a
light-mode ground, passes every existing check. Dark mode in particular has no automated coverage
whatsoever, and it is the app's primary use case: solo play happens at night.
</problem>

<pattern>
`frontend/tests/e2e/play.spec.ts` is the model. It registers a unique
`e2e-${Date.now()}@example.test` user through the auth gate, picks the seeded "Scene-Sequel Demo"
system, advances Scene → Sequel and writes a journal entry — using role and label queries
exclusively. Follow its structure, its seeding assumptions and its selector style.

Playwright's `colorScheme` context option drives `prefers-color-scheme`, which is how you exercise
both themes without a UI toggle.
</pattern>

<instructions>
1. Confirm the baseline is worth freezing: run the full frontend suite and look at the app in both
   themes. If anything is visibly wrong, report it and stop rather than baselining a defect.

2. Add screenshot baselines with `toHaveScreenshot()` for: sign-in, campaign list, start a
   campaign, the game master console, the console with the oracle drawer open, and the console
   with the dice widget open — **each in both colour schemes**, via Playwright's `colorScheme`
   context option.

   Mask anything non-deterministic — timestamps, generated ids, the `updatedAt` strings on the
   campaign list — or the suite will be flaky and will be ignored within a week. Flaky visual
   tests are worse than none.

3. Add `@axe-core/playwright` and assert **zero serious or critical violations** on each of those
   screens, in both themes. Start at serious/critical rather than at zero-violations-of-any-kind,
   so the gate is meaningful on day one; note in your report what a stricter setting would
   currently flag.

4. Add explicit dialog-behaviour assertions for both drawers, covering what prompt 19 built:
   Escape closes, focus is trapped while open, focus returns to the trigger on close.

5. Wire it into CI. If `.github/workflows/ci.yml` exists (prompt `01-ci-pipeline.md`), add it to
   the E2E job and upload image diffs as artifacts on failure. If not, note in your report that it
   needs adding when CI lands.

6. Document the workflow — how to update a baseline deliberately
   (`npx playwright test --update-snapshots`), and that an unexplained baseline update in a PR is
   a review flag, not a routine step. A visual baseline nobody may question is not a gate.
   Add it to `AGENTS.md` alongside the existing merge gates (Constitution VI).
</instructions>

<constraints>
- Baselines must be generated in a consistent environment. Note in the docs which platform
  produced them and whether CI regenerates or compares — cross-platform font rendering differences
  are the usual reason visual suites get disabled.
- Do not lower the a11y threshold to make a screen pass. If a violation is real, report it; if it
  is genuinely a false positive, disable that specific rule for that specific screen with a
  comment explaining why.
- Do not commit large numbers of baseline images beyond the screens listed. Twelve screenshots is
  a maintainable set; fifty is abandonware.
- Do not change application code. If a screenshot or an axe assertion reveals a defect, report it
  as a finding — fixing it belongs in its own PR.
- Do not add a third-party visual-diffing service.
</constraints>

<acceptance_criteria>
    npm run test:e2e
    # expected: green, including 12 screenshot baselines and the axe assertions

    # the gate actually catches regressions — verify all three, then revert each:
    # 1. remove one aria-label   -> the axe or role-based assertion fails
    # 2. change a colour token   -> the screenshot comparison fails
    # 3. remove the Escape handler from a drawer -> the dialog-behaviour test fails

    make lint && make test
    # expected: unchanged and green

Run the E2E suite three times in a row with no code changes; it passes all three times. Report if
it does not — a suite that fails one run in three will be disabled rather than fixed.
</acceptance_criteria>

<completion>
Branch `visual-regression` off an updated `master`. Commit atomically with short imperative
subjects; keep the baseline images in their own commit so the diff of the test code is readable.

Before finishing, run and report `npm run test:e2e`, `make lint` and `make test`.

If a gate fails, report its output verbatim and stop. Never weaken a gate to make it pass. Do not
create or push git remotes.

Report: what is baselined, the result of the three deliberate-regression checks, the flakiness
check, what a stricter axe setting would currently flag, and any defect the new gates revealed.
</completion>
