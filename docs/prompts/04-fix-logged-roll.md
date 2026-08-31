# 04 · Fix the crash when logging a dice roll

Wave 2 · after `01-ci-pipeline` · branch `fix-logged-roll` · ~2 h · fixes audit finding **A5** (critical)

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js talking to it **only**
through the OpenAPI contract at `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`
(Constitution Principle V).

The dice roller lets a player roll `NdM±K` and optionally log the result into the campaign
journal. Logging posts to a dedicated endpoint that rolls *and* journals server-side, so the
displayed result is replaced by exactly what was recorded.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `.specify/memory/constitution.md` — Principle V, contract-first
- `docs/audit/spec-compliance.md` §6 finding A5
- `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml` lines 363-385
</context>

<preconditions>
The stack must be running and seeded — this defect is invisible from the source and from every
existing test:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
Pressing **Log to journal** in the player app's dice widget replaces the entire page with
*"Application error: a client-side exception has occurred"*. The roll itself is persisted
correctly — reloading shows it in the journal — but the app has to be reloaded.

Two layers, and both need fixing.

**Layer 1 — a contract violation.** `POST /api/campaigns/{campaignId}/rolls` returns IRI strings
where the contract requires embedded objects.
`specs/001-solo-ttrpg-assistant/contracts/openapi.yaml:382-383` declares:

    roll: { $ref: '#/components/schemas/DiceRollResult' }
    journalEntry: { $ref: '#/components/schemas/JournalEntry' }

The runtime returns:

    $ curl -s -H 'Accept: application/json' -X POST \
        "$BASE/api/campaigns/$CID/rolls" -H "Authorization: Bearer $TOKEN" \
        -H 'Content-Type: application/json' -d '{"notation":"1d20+5"}'
    {"roll":"\/api\/.well-known\/genid\/b3984bd9e95e94a4c185",
     "journalEntry":"\/api\/campaigns\/4a224ef4-b5d4-4be2-a06b-60354e06f52d\/journal"}

Note the `journalEntry` IRI even names a different campaign id than the one posted to.

Cause: `backend/src/Dice/Infrastructure/Api/DiceRollResource.php` declares `LoggedRollResource`
with two properties typed as `DiceRollResource` and
`App\Journal\Infrastructure\Api\JournalEntryResource`. Both of those classes carry `#[ApiResource]`,
and API Platform serialises a nested API resource as an IRI reference by default rather than
embedding it.

**Layer 2 — unguarded consumption.** `frontend/src/app/(play)/campaigns/[id]/page.tsx:195` does

    setDiceResult(logged.roll);

with `logged.roll` cast to `DiceRollResultView` but actually a string, and
`frontend/src/components/dice/DiceRollerWidget.tsx:106` then calls

    {result.diceValues.map((value, index) => (

on it. Captured `pageerror`: `Cannot read properties of undefined (reading 'map')`.

**Why every existing gate misses this** — and this part matters as much as the fix:
- Behat asserts on the resulting journal row, not on the response body.
- Vitest passes `DiceRollResultView` props into the component directly, so it never sees the wire
  shape.
- The Playwright smoke never touches dice.
- `scripts/check-contract.sh` compares paths, methods and schema *declarations*; it never fetches
  a response body, so a correctly declared and wrongly serialised endpoint passes it cleanly.

Fixing only the serialisation would leave that blind spot open, which is why step 2 below adds a
body-shape assertion.
</problem>

<instructions>
1. Confirm the diagnosis still holds. Register a player over the API, start a campaign on the
   seeded "Scene-Sequel Demo" system, and `curl` the `/rolls` endpoint as shown above. Check that
   `roll` comes back as a string. If it is already an object, stop and report.

2. Write the failing test first, at the layer that was blind. Add a Behat scenario to
   `backend/features/dice/notation.feature` — or a step in the existing "The log action appends
   the roll to the journal" scenario — that asserts on the **response body**: `roll` is an object
   carrying `notation`, `diceValues`, `modifier` and `total`, and `journalEntry` is an object
   carrying `id`, `stageName`, `kind` and `createdAt`. Use the existing
   `backend/tests/Acceptance/Context/DiceContext.php`. Confirm it fails.

3. Fix the serialisation so the nested resources embed. The most direct route is
   `#[ApiProperty(readableLink: true)]` on both properties of `LoggedRollResource`; if that does
   not produce the contract shape, the alternative is to give `LoggedRollResource` plain
   non-`ApiResource` DTOs mirroring the two payloads. Prefer whichever keeps a single source of
   truth for the journal-entry shape — the `deptrac.yaml` comment on the
   `DiceInfrastructure -> JournalInfrastructure` edge records that embedding *the* canonical
   `JournalEntryResource` rather than duplicating it was a deliberate decision, so do not
   duplicate that shape without saying why in the commit message.

4. Guard the consumer. In `frontend/src/app/(play)/campaigns/[id]/page.tsx`, do not assume the
   response shape: narrow it before calling `setDiceResult`, and if it is not the expected object
   surface the existing dice error path rather than letting the render throw. A malformed
   response should degrade to a visible message, never to a blank page.

5. Add a Vitest case for `DiceRollerWidget` proving it does not crash when handed a malformed
   `result`. The suite already asserts "a refusal never shows a result, not even a stale one";
   this is the same class of guarantee.

6. Re-run `scripts/check-contract.sh` and confirm it still passes. It will — that is the point,
   and it is why prompt `15-contract-gate-payloads.md` exists. Mention in your PR description
   that this defect passed the contract gate.

7. Update `docs/functional-guide.md` in the same change set (Constitution VI): §5.4 carries a
   "Known defect" block about the crash and §7 flags the endpoint with a warning; §9 has a
   troubleshooting row. Remove all three.
</instructions>

<constraints>
- Do not change the contract. `openapi.yaml` is correct; the implementation is wrong. If you
  believe the contract should change, stop and report rather than editing it — a contract change
  requires a versioned migration path under Constitution V.
- Do not change the dice domain, the parser, the bounds, or `RollAndLogHandler`. The roll is
  computed and persisted correctly; only the response projection and its consumer are broken.
- Do not widen `scripts/check-contract.sh` here — that is prompt 15, deliberately separate.
- Out of scope: the dice widget's visual design (prompts 18–19).
</constraints>

<acceptance_criteria>
    # response body carries objects, not IRI strings
    curl -s -H 'Accept: application/json' -X POST "$BASE/api/campaigns/$CID/rolls" \
      -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
      -d '{"notation":"2d6+3"}' | jq -e '.roll.diceValues | length == 2'
    # expected: exit 0, and .journalEntry.stageName is the campaign's current stage

    docker compose exec php vendor/bin/behat
    # expected: green, including the new body-shape assertion

    make lint && make test
    scripts/check-contract.sh
    # expected: all green

Manually: roll `2d6+3` in the player app, press **Log to journal**, and confirm the result stays
on screen, "Logged to your journal." appears, the entry shows in the journal, and the page does
not blank. Check the browser console is free of `pageerror`.

No occurrence of the A5 "Known defect" wording remains in `docs/functional-guide.md`.
</acceptance_criteria>

<completion>
Branch `fix-logged-roll` off an updated `master`. Commit atomically with short imperative
subjects; one logical change per commit (`AGENTS.md`: "Task = commit"). The failing test lands
before the fix.

Before finishing, run and report `make lint`, `make test`, `scripts/check-contract.sh`, and
`npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, and anything you could not verify.
</completion>
