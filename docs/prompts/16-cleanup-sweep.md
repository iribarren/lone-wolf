# 16 · Lower-severity cleanup sweep

Wave 5 · no dependencies · branch `cleanup-sweep` · ~half a day · fixes **C2–C8, C10–C12**

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js talking to it only
through the OpenAPI contract.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `docs/audit/spec-compliance.md` §6, the "Lower severity" table — the source of this list

This is a housekeeping sweep of eleven independent, individually small items. Work them as a
series of small commits in one session, in the order below. **Any item that turns out to be
larger than described, or that you disagree with, should be dropped from the PR and reported —
not expanded into a refactor.** That judgement is expected; exercise it.

Two neighbouring items are deliberately **not** here: C1 (`Bearer undefined`) belongs to prompt
`08-session-lifecycle.md`, and C9 (the unused `openrtk` dependency) belongs to prompt
`11-toolchain-hygiene.md`. If those have not run, leave both alone.
</context>

<preconditions>
The stack must be running for the items that need verification:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start, and after each item.
</preconditions>

<problem>
Eleven small defects, each verified during the audit of 2026-08-30. None is urgent; together they
are the difference between a codebase that is nearly right and one that is right.

**C2 — the login endpoint has no contract and no types.** `POST /api/auth/login` is served by the
Lexik `json_login` firewall listener, never reaches API Platform's metadata, and so is absent from
the generated `frontend/src/lib/api/schema.gen.ts`. `AuthGate.tsx` reaches it via the
`apiPath('/api/auth/login')` escape hatch — a raw cast — so the request body and response of the
single most security-relevant call are entirely untyped. In a project whose Principle V is
contract-first, this is the one uncontracted hole in the integration surface.

**C3 — persistence class names leak into the admin UI.** The backoffice shows *"Create
PersistenceOracle"* and *"Edit PersistenceGameSystem"* as page headings. No CRUD controller calls
`setEntityLabelInSingular()` / `setEntityLabelInPlural()`.

**C4 — test scaffolding renders to real users.** `DiceRollerWidget.tsx:57` renders
`<p>Dice roller closed.</p>` and `OracleDrawer.tsx:56` renders `<p>Oracles drawer closed.</p>`
when closed, instead of returning `null`. Both are visible on the live game master console.

**C5 — an empty migration and asymmetric `down()` methods.**
`backend/migrations/Version20260822232549.php` is an unmodified auto-generated stub: empty
`getDescription()`, empty `up()`, empty `down()`. Separately,
`Version20260823075023::down()` drops `uniq_users_email` but not the `game_systems` unique index,
and `Version20260823210000::down()` / `Version20260824010000::down()` drop tables without first
dropping their foreign keys and indexes — harmless in PostgreSQL, but inconsistent with
`Version20260823110000::down()`, which does it properly.

**C6 — one inconsistent column type.** `PersistenceUser.php:27` maps `roles` as
`#[ORM\Column(type: 'json')]` while every other document column in the schema uses the project's
custom `jsonb` type (`App\Shared\Infrastructure\Persistence\Types\JsonbType`).

**C7 — dead application code.** `backend/src/Rulesets/Application/SetSystemStatusHandler.php` and
`Command/SetSystemStatusCommand.php` have no callers in `src/` or `tests/`. System status is set
through a raw `ChoiceField` in `SystemCrudController` instead, bypassing the handler.

**C8 — the documented quickstart fails as written.** `.env.dist:13-14` ship `ADMIN_EMAIL` and
`ADMIN_PASSWORD` commented out, so the `README.md` step
`docker compose exec php bin/console app:create-admin` fails with *"Provide a valid email via
--email or $ADMIN_EMAIL."* Every new contributor hits this.

**C10 — the architecture notes omit a bounded context.** `docs/architecture.md` lists the bounded
contexts under "Bounded contexts (Constitution II)" and does not mention `Identity`, which exists
in `backend/src/Identity/` and owns users, roles, JWT issuance and the admin login.

**C11 — content negotiation contradicts the contract.** `backend/config/packages/api_platform.yaml`
lists `jsonld` before `json`, so JSON-LD is the default. A client following `openapi.yaml`
literally — which documents only `application/json` — receives Hydra envelopes
(`{"@context":…,"member":[…]}`) unless it sets an `Accept` header. The app works only because
`ApiClient.request` always sets one.

**C12 — one untested authorization path.** `PATCH /api/characters/{characterId}` is the only
campaign-scoped write with no operation-level `CAMPAIGN_OWNER` expression. Ownership *is* enforced
— `UpdateCharacterHandler` resolves the character's campaign through `OwnedCampaignFetcher`, so a
foreign player gets a 404 — but no test covers it. Every other ownership path has one.
</problem>

<instructions>
Work these in order, one commit each. Confirm each diagnosis before acting on it; if an item no
longer reproduces, skip it and say so.

1. **C4** (user-visible, do it first). Return `null` from both components when closed. Update the
   two Vitest cases that currently assert on the placeholder text so they assert its *absence*.

2. **C3.** Add `setEntityLabelInSingular()` / `setEntityLabelInPlural()` to all three CRUD
   controllers — `SystemCrudController`, `GameFlowCrudController`, `OracleCrudController` — using
   the domain's language: "Game system", "Campaign flow", "Oracle table".

3. **C8.** Uncomment `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env.dist` with obviously-placeholder
   values, and make sure `README.md` and `docs/functional-guide.md` §3 agree with whatever you
   choose. `docs/functional-guide.md` currently carries an explicit note about this failure and a
   troubleshooting row; remove them if the quickstart now works as written.

4. **C10.** Add `Identity` to the bounded-context list in `docs/architecture.md`, in the same
   one-line-charter style as the others.

5. **C12.** Add a test that a foreign player's `PATCH /api/characters/{characterId}` returns 404.
   `backend/tests/Integration/Characters/DriftFlaggingTest.php` already has a "foreign players
   cannot list characters" case — follow it.

6. **C7.** Decide deliberately: either wire `SetSystemStatusHandler` into `SystemCrudController`
   so status changes go through the application layer like every other write, or delete the
   handler and its command as speculative abstraction. **Wiring it in is the better answer** —
   Constitution I puts use cases in the Application layer and the CRUD controller currently
   bypasses it — but if you delete instead, justify it. Either way, no half state.

7. **C6.** Change `PersistenceUser.roles` to the `jsonb` type and add the migration that alters
   the column. Verify existing rows survive: seed a user, migrate, and read it back.

8. **C5.** Delete the empty `Version20260822232549.php` **only after** confirming it is not
   recorded as executed in any environment you care about — check the
   `doctrine_migration_versions` table. If it may have run anywhere, leave it and add a
   `getDescription()` explaining it is an intentional no-op. Then make the three asymmetric
   `down()` methods consistent with `Version20260823110000::down()`.

9. **C11.** This is a documentation decision, not a code change. Do **not** reorder the formats in
   `api_platform.yaml` — that would change the default response type for every existing consumer.
   Instead document the behaviour: note in `openapi.yaml` (or its accompanying prose) and in
   `docs/functional-guide.md` §7 that clients must send `Accept: application/json`. The functional
   guide already says this; make sure the contract does too.

10. **C2.** The hardest item, and the one most likely to grow. Bring `/api/auth/login` into the
    typed surface. Options, in order of preference: declare it as a documented API Platform
    operation whose processor delegates to the existing firewall behaviour; or add a
    hand-maintained typed wrapper in `frontend/src/lib/api/` with an explicit comment explaining
    why it cannot be generated, plus a contract test asserting the request and response shapes.
    **If the first option turns out to require restructuring the firewall, stop, take the second,
    and report the constraint.** Do not restructure authentication in a cleanup PR.
</instructions>

<constraints>
- One commit per item, each independently revertible. Do not batch.
- Do not turn any item into a refactor. If an item cannot be done in its stated scope, drop it
  from the PR and report why — an honest partial sweep is the correct outcome.
- Do not touch C1 or C9; they belong to prompts 08 and 11.
- Do not change the domain layer for any of these. Every item is infrastructure, configuration,
  or documentation.
- Do not reorder `api_platform.yaml` formats (item 9) — the fix is documentation.
- Do not delete a migration that may have executed anywhere (item 8).
- Out of scope: visual design of the two components in item 1 — return `null` and nothing more.
</constraints>

<acceptance_criteria>
    make lint && make test
    npm run test && npm run typecheck && npm run lint
    npm run test:e2e
    scripts/check-contract.sh
    # expected: all green after every commit, not just at the end

Per item:
- C4 — the live game master console shows no "closed." text anywhere
- C3 — no admin page heading contains the string "Persistence"
- C8 — a clean `cp .env.dist .env` followed by the README's `app:create-admin` step succeeds
  verbatim
- C10 — `grep -c Identity docs/architecture.md` is greater than 0
- C12 — the new test fails if you remove the `OwnedCampaignFetcher` call from
  `UpdateCharacterHandler` (check this, then restore)
- C6 — `\d users` in psql shows `jsonb`, and pre-existing users still authenticate
- C5 — `doctrine:migrations:status` is clean and a fresh `migrate` from an empty database succeeds
- C11 — the contract states the `Accept` requirement
- C2 — `/api/auth/login` is reachable from the frontend without a raw `apiPath()` cast, or the
  wrapper carries a test and a comment explaining why
</acceptance_criteria>

<completion>
Branch `cleanup-sweep` off an updated `master`. One commit per item, with a short imperative
subject naming the item (`C4: return null from closed dice and oracle panels`).

Before finishing, run and report `make lint`, `make test`, `npm run test:e2e` and
`scripts/check-contract.sh`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass. Do not create or push git remotes.

Report: an item-by-item table of done / skipped / dropped with the reason for anything not done,
the decision you took on C7 and C2, and anything you discovered that belongs in the audit but is
not yet recorded there.
</completion>
