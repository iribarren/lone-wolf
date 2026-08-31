# 03 · Fix the campaign-flow editor

Wave 1 · after `02-fix-admin-url` · branch `fix-flow-editor` · ~2 h · fixes audit findings **A2** (critical) and **A3** (high)

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js.

The "Campaign flows" section of the EasyAdmin backoffice is where an admin defines a system's
stages, its legal stage-to-stage transitions, and which single stage new campaigns start on.
Symfony ships no JavaScript for adding and removing collection rows, so this editor is glued
together by a hand-written asset that also keeps the stage dropdowns in sync with the stage rows.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `docs/audit/spec-compliance.md` §6 findings A2 and A3
- `backend/public/assets/admin-flow-editor.js` — the file you are fixing (it was
  `backend/public/admin/flow-editor.js` before prompt 02 moved it; if prompt 02 has not run yet,
  it is still at the old path)
- `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php` and
  `backend/src/Rulesets/Infrastructure/Admin/Form/FlowDefinitionType.php`
</context>

<preconditions>
Prompt `02-fix-admin-url.md` must have landed, or you cannot open the page this prompt is about.

The stack must be running and seeded:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:create-admin --email=admin@example.test --password='admin-passphrase'
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
Two separate bugs in the same file make the campaign-flow editor unusable.

**A2 — every stage dropdown is empty on page load.** Open any system under "Campaign flows". The
*Starting stage* select, and the *from* and *to* selects of every transition row, render with a
single empty option. No stage is selectable, and the stage that is actually stored is not
pre-selected. An admin cannot define a legal transition or designate a starting stage, so FR-003
and FR-004 are unsatisfiable through the UI and user story US1 fails its own independent test.

Root cause: the file's init path ends with

    syncSelects(document.body);

and `syncSelects` opens with

    function syncSelects(fromElement) {
        var form = fromElement.closest('form');
        if (!form) {
            return;
        }

`document.body.closest('form')` is always `null`, so the initial population silently no-ops. The
selects only ever fill when a stage-name input subsequently fires `blur` or `change`, because
those handlers pass the input itself rather than `document.body`.

**A3 — the dropdowns then offer the game system's own name as a stage.** Once populated by a
blur, the options include the system name. Root cause: `stageNames()` collects with

    scope.querySelectorAll('input[name$="[name]"]')

which matches `PersistenceGameSystem[name]` — the system's own name field — as well as the stage
rows. Note that the sibling wiring code lower in the same file already uses the correct, narrower
selector `input[name*="[stages]"][name$="[name]"]`.

Evidence captured 2026-08-30 in Chromium, editing the seeded "Smoke Quest" system:

    document.body.closest("form") === null   ->  true

    starting_stage options on load           ->  [""]
    starting_stage options after blurring a stage-name input
                                             ->  ["", "Smoke Quest", "Scene", "Sequel"]

`Smoke Quest` is the system. `Scene` and `Sequel` are the only real stages.
</problem>

<pattern>
The correct narrow selector already exists in this same file, in the block that wires the
change/blur listeners:

    document.querySelectorAll('input[name*="[stages]"][name$="[name]"]')

Reuse exactly that selector in `stageNames()`. Do not invent a third variant.
</pattern>

<instructions>
1. Confirm the diagnosis still holds before changing anything. Read the whole of
   `backend/public/assets/admin-flow-editor.js` — it is under 200 lines — and locate
   `syncSelects`, `stageNames` and `enhance`. Open the flow editor in a browser and check the
   selects are still empty on load. If the behaviour has changed, stop and report.

2. Write the failing tests first. Extend `frontend/tests/e2e/admin.spec.ts` (created by prompt
   02) with a case that signs in, opens the "Campaign flows" editor for the seeded
   "Scene-Sequel Demo" system, and asserts, **without interacting with any field beforehand**:
   - the *Starting stage* select offers one option per stage, and `Scene` is the selected value
     (that is the system's stored starting stage)
   - each transition row's *from* and *to* selects offer the same stage options, with the stored
     values selected
   - none of those selects offers `Scene-Sequel Demo`, the system's own name

   Confirm both assertions fail for the reasons above before fixing anything.

3. Fix A2. `syncSelects` bailing on a non-form element is reasonable defensive behaviour — the
   bug is the caller. In `enhance()`, replace the `syncSelects(document.body)` call so that it
   runs once per form on the page, for example by iterating `document.querySelectorAll('form')`.
   Do not delete the `closest('form')` guard; the event-driven callers depend on it.

4. Fix A3. Narrow the selector in `stageNames()` to
   `input[name*="[stages]"][name$="[name]"]`, matching the sibling code quoted above.

5. Verify that the stored values are pre-selected, not merely present as options. If populating
   the option list clears an existing `select.value`, preserve and restore it — the function
   already reads `var current = select.value` before rebuilding the options, so confirm that
   value is reapplied afterwards and fix it if not. An editor that offers the right options but
   silently discards the stored starting stage on save would be worse than the current bug.

6. Update the documentation in the same change set (Constitution VI). `docs/functional-guide.md`
   §4.3 carries a "Known defects in this editor" block describing both bugs and a workaround, and
   §9 has a matching troubleshooting row. Remove both.
</instructions>

<constraints>
- Fix only these two bugs. The editor's ergonomics — no drag-to-reorder, no live graph preview,
  the plain add/remove buttons — are out of scope.
- Do not rewrite the file in a framework or introduce a build step for it. It is deliberately a
  small vanilla-JS asset with no toolchain.
- Do not change the PHP form types (`FlowDefinitionType`, `FlowStageType`,
  `FlowTransitionType`, `StageNameChoiceType`, `LenientStageNameLoader`) or the domain. The
  server side is correct and is covered by
  `backend/tests/Unit/Rulesets/Infrastructure/Form/FlowDefinitionTypeTest.php` and
  `backend/tests/Integration/Rulesets/FlowModificationGuardTest.php`; both must stay green.
- Do not touch the occupied-stage guard (FR-005). It works.
</constraints>

<acceptance_criteria>
    cd frontend && npm run test:e2e
    # expected: the two new admin.spec.ts assertions pass without any pre-interaction

    make lint && make test
    # expected: green, including FlowDefinitionTypeTest and FlowModificationGuardTest

Manually, in a browser, on the seeded "Scene-Sequel Demo" system, **without clicking into any
field first**:
- *Starting stage* offers `Setup`, `Scene`, `Sequel` and shows `Scene` selected
- transition rows show their stored `from`/`to` values selected
- no select offers `Scene-Sequel Demo`
- adding a stage row, typing a name, and blurring adds that name to every dropdown
- saving without touching anything leaves the stored flow byte-identical

No occurrence of the A2/A3 "Known defects" wording remains in `docs/functional-guide.md`.
</acceptance_criteria>

<completion>
Branch `fix-flow-editor` off an updated `master`. Commit atomically with short imperative
subjects; one logical change per commit (`AGENTS.md`: "Task = commit"). Failing tests land before
the fixes.

Before finishing, run and report `make lint`, `make test`, and `npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, and anything you could not verify — in particular
say explicitly whether the stored starting stage survives a no-op save.
</completion>
