# 22 · Make the backoffice able to save anything

Wave 1 · after `03-fix-flow-editor`, before `05-oracle-authoring` · branch `fix-admin-save` · ~3 h ·
fixes finding **A6** (critical)

> **A6 is not in the original audit.** It was found on 2026-08-31 while running
> `03-fix-flow-editor`, whose fixes make the flow editor *display* correctly and thereby made the
> save path reachable for the first time. The audit dated 2026-08-30 verified the backoffice by
> reading rendered pages; nobody pressed Save. Treat the evidence in `<problem>` as the finding.

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js. The admin backoffice is
EasyAdmin, server-rendered inside the backend, behind a session firewall at `/admin/login`.

Every persistence entity in this codebase follows one deliberate shape: private typed properties,
accessors *named exactly like the property* (`title()`, `flowDefinition()`), and a single
`replace()` method that applies a whole domain snapshot. There are no per-property setters
anywhere — `grep -rn "public function set" backend/src` returns nothing on any entity.

That shape is what breaks EasyAdmin, which writes submitted fields back through Symfony's
PropertyAccess.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `.specify/memory/constitution.md` — Principles I (hexagonal layers) and III (SOLID, strict types)
- `backend/src/Rulesets/Infrastructure/Persistence/PersistenceGameSystem.php` — `flowDefinition()`
  (line 84), `sheetStructure()` (line 90), `replace()` (line 102)
- `backend/src/Oracles/Infrastructure/Persistence/PersistenceOracle.php` — `title()` (line 64),
  `entries()` (line 80), `replace()` (line 90)
- `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php`,
  `SystemCrudController.php`, `UpdatesFlowDefinition.php`
- `backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` — the test to extend
</context>

<preconditions>
Prompts `02-fix-admin-url` and `03-fix-flow-editor` must have landed. Before 03, the flow editor's
selects were empty and this bug was masked behind a form nobody could fill in.

The stack must be running and seeded:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:create-admin --email=admin@example.test --password='admin-passphrase'
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
**No EasyAdmin form in this application can be saved.** Every save returns HTTP 500 with a
`NoSuchPropertyException` before the controller's `updateEntity()` ever runs. This is not a
regression — `git log -S "setFlowDefinition"` and `git log -S "public function setTitle"` are both
empty, so the write path has never worked since `e9b3b84` introduced the entities.

Root cause: Symfony's `DataMapper::mapFormsToData()`
(`vendor/symfony/form/Extension/Core/DataMapper/DataMapper.php:73`) writes every mapped, submitted
field back onto the bound object:

    if ($config->getMapped() && $form->isSubmitted() && $form->isSynchronized()
        && !$form->isDisabled() && $this->dataAccessor->isWritable($data, $form)) {
        $this->dataAccessor->setValue($data, $form->getData(), $form);
    }

PropertyAccess treats a method named exactly like the property as a candidate mutator, so
`isWritable()` returns **true** for `flowDefinition` on the strength of `flowDefinition()` — then
`setValue()` calls it with one argument and the zero-argument getter blows up.

Evidence captured 2026-08-31 in Chromium against the seeded stack, on three independent forms:

    /admin/game-flow/{id}/edit  · change Starting stage "Scene" -> "Setup" · Save changes
      -> HTTP 500  NoSuchPropertyException
         The method "flowDefinition" in class "App\Rulesets\Infrastructure\Persistence\
         PersistenceGameSystem" requires 0 arguments, but should accept only 1.

    /admin/game-flow/{id}/edit  · touch nothing at all · Save changes
      -> HTTP 500, same exception

    /admin/system/{id}/edit     · edit the sheet-structure JSON · Save changes
      -> HTTP 500  NoSuchPropertyException
         The method "sheetStructure" in class "...PersistenceGameSystem" requires 0 arguments

    /admin/oracle/{id}/edit     · append " X" to the table title · Save changes
      -> HTTP 500  NoSuchPropertyException
         The method "title" in class "App\Oracles\Infrastructure\Persistence\
         PersistenceOracle" requires 0 arguments

Stored rows are byte-identical after each attempt, because the request dies before the flush.
Note the second case: **a no-op save crashes too**, so "the data survived" is not evidence of
anything working.

*Why this matters.* FR-002, FR-003 and FR-004 are authoring requirements: US1 cannot pass its
independent test with a backoffice that renders a correct form and then refuses to persist it.
`05-oracle-authoring` is blocked outright — it adds an entries field to a form whose Save button
returns 500.

*Why no test caught it.* `AdminGameFlowPagesTest` only ever issues `GET`s, and Behat drives the
API, not the admin forms. There is no test anywhere that submits an EasyAdmin form.
</problem>

<pattern>
`backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` already boots a `KernelBrowser`,
signs an admin in and walks the backoffice — its `adminClient()` helper (line 113) and
`createSystem()` fixture (line 48) are exactly what the new tests need. It stops at `GET`; extend
it to submit:

    $crawler = $client->request('GET', '/admin/game-flow/'.$id.'/edit');
    $form = $crawler->selectButton('Save changes')->form();
    $client->submit($form);

    self::assertResponseIsSuccessful();   // fails today with 500

For the entity change, imitate the existing snapshot API rather than inventing a new one:
`PersistenceGameSystem::replace()` (line 102) and `PersistenceOracle::replace()` (line 90) are the
established way this codebase mutates a persistence row.
</pattern>

<instructions>
1. Confirm the diagnosis still holds before changing anything. Sign in to `/admin`, open the
   Campaign flows editor for the seeded "Scene-Sequel Demo" system, change *Starting stage* from
   `Scene` to `Setup` and press *Save changes*. You must get an HTTP 500
   `NoSuchPropertyException` naming `flowDefinition`. Repeat on `/admin/oracle/{id}/edit` by
   editing a table title. If either save now succeeds, stop and report — the bug has moved.

2. Write the failing tests first, in `AdminGameFlowPagesTest` (and a sibling for oracles, e.g.
   `backend/tests/Integration/Oracles/AdminOracleSaveTest.php`). Cover, at minimum:
   - a **no-op save** of the Campaign flows editor returns 2xx/3xx and leaves the stored
     `flow_definition` payload byte-identical;
   - a **real edit** — move the starting stage from `Scene` to `Setup` — returns successfully and
     the stored payload reflects exactly that one change, with stages and transitions untouched;
   - editing an oracle's title persists the new title.

   Confirm all of them fail with the 500 above before touching production code.

3. Fix the write path. The minimal change that keeps every CRUD controller working as written is
   to give each persistence entity real setters for the properties its admin form maps —
   `PersistenceGameSystem::setFlowDefinition()` / `setSheetStructure()`,
   `PersistenceOracle::setTitle()` / `setScopeType()` / `setScopeSystemId()`, and the equivalents
   on any other entity behind a CRUD controller. Derive the list from what each controller's
   `configureFields()` actually maps — for oracles today that is `title`, `scopeType` and
   `scopeSystemId` only; `entries` is not on the form yet (that is **A4**, prompt 05, which will
   need `setEntries()` when it adds the field). Setters must carry the same PHPStan array shapes as
   the matching getters (`FlowPayload`, `SheetPayload`, `list<OracleEntryPayload>`) so
   `composer lint` stays clean at level max.

   Keep `replace()` exactly as it is and keep using it for domain-snapshot writes; the setters
   exist only for the ORM/form adapter boundary. Do not make the properties public, and do not
   weaken a type to make the mapper happy.

   `updateEntity()` in `GameFlowCrudController` and `SystemCrudController` reads the submitted
   payload back off the entity (`UpdatesFlowDefinition::updateFlowCommand()`), so once the mapper
   can write, the existing validate-then-save logic works unchanged. Verify that is true rather
   than assuming it.

4. Verify the domain guards still bite through the form. With the write path restored, the
   application handler finally receives real submissions — prove FR-005 still refuses an edit that
   orphans an occupied stage, and that the refusal surfaces as the flash message rather than an
   exception. `FlowModificationGuardTest` must stay green.

5. Confirm the optimistic-lock path. `PersistenceGameSystem` carries `#[ORM\Version]`; check that a
   concurrent supersede still produces the `SUPERSEDED_MESSAGE` warning flash and not a 500.

6. Update the documentation in the same change set (Constitution VI). `docs/functional-guide.md`
   §4.3 and §4.4 describe authoring flows and oracles as if saving worked; make them accurate.
   Add A6 to the defects table only if it is still open when you finish — if you fixed it, it does
   not belong there.
</instructions>

<constraints>
- Fix the save path only. Do not redesign the backoffice, do not add fields, do not touch the
  oracle entries authoring gap (**A4**) — that is `05-oracle-authoring`.
- Do not change the domain or application layers. `FlowDefinition`, `FlowFactory`,
  `UpdateFlowDefinitionHandler` and the value objects are correct and are covered by
  `FlowDefinitionTypeTest` and `FlowModificationGuardTest`; both must stay green.
- Do not change the flow-editor JavaScript (`backend/public/assets/admin-flow-editor.js`) or its
  form theme. Prompt 03 fixed the client side; this is purely server-side.
- Persistence entities are Infrastructure, so setters there do not breach Principle I — but they
  must not leak into the Domain layer. Deptrac must stay clean.
- Do not weaken PHPStan array shapes or add baseline entries to get past level max.
</constraints>

<acceptance_criteria>
    make lint && make test
    # expected: green, including the new save tests, FlowDefinitionTypeTest
    # and FlowModificationGuardTest

    cd frontend && npm run test:e2e
    # expected: green — prompt 03's flow-editor assertions still pass

Manually, in a browser, on the seeded "Scene-Sequel Demo" system:
- changing *Starting stage* from `Scene` to `Setup` and saving returns to the editor with no
  exception, and `select flow_definition from game_systems where name='Scene-Sequel Demo'` shows
  `"starting_stage": "Setup"` with stages and transitions unchanged
- saving again without touching anything leaves that payload byte-identical
- adding a transition row and saving persists it
- editing an oracle's title under `/admin/oracle` persists the new title
- an edit that orphans an occupied stage is still refused with the FR-005 flash message, not a 500

    docker compose exec php bin/console doctrine:schema:validate --skip-sync
    # expected: "[OK] The mapping files are correct."
    # (--skip-sync is deliberate: the database-sync half of this command already
    #  fails on master, before and after this change. Do not try to fix that here.)
</acceptance_criteria>

<completion>
Branch `fix-admin-save` off an updated `master`. Commit atomically with short imperative subjects;
one logical change per commit (`AGENTS.md`: "Task = commit"). Failing tests land before the fixes.

Before finishing, run and report `make lint`, `make test`, and `npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, and anything you could not verify — in particular
say explicitly which admin forms you saved successfully in a real browser, and whether any CRUD
controller still has a mapped property with no setter.
</completion>
