# Quickstart: Validating Lone Wolf End-to-End

**Feature**: `001-solo-ttrpg-assistant` | **Date**: 2026-08-22

A runnable validation guide proving the feature works end-to-end after each implementation phase.
Implementation details live in [plan.md](./plan.md) and future `tasks.md`; deep data rules in
[data-model.md](./data-model.md); endpoint payloads in [contracts/openapi.yaml](./contracts/openapi.yaml).

## Prerequisites

- Docker Engine + Docker Compose v2
- `curl` (or HTTPie) for contract probes
- Ports free: 8080 (backend via nginx), 3000 (frontend), 5432 (postgres)

## Setup

```bash
cp .env.dist .env            # adjust local secrets; never commit .env
docker compose up --build    # boots php-fpm + nginx, postgres, next dev
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php bin/console app:seed:demo        # demo system(s), oracles (optional content seed)
```

Expected: backend health responds at `http://localhost:8080/api/health` with `{"status":"ok"}`;
frontend placeholder renders at `http://localhost:3000`.

## Validation Scenarios

### V1 — Admin defines a system + flow (US1, FR-001..006)

1. Log into the EasyAdmin backoffice (`http://localhost:8080/admin`) as seeded admin.
2. Create game system "Scene-Sequel Demo": stages `Setup → Scene → Sequel → Setup` transitions,
   starting stage `Scene`; add a second system "Act Ladder": `Act I → Beat → Act II`, start `Act I`;
   and a third system "Freeform Sandbox": single stage `Free Play` with no outgoing transitions
   (exercises the terminal-guidance path).
3. Create all three systems; confirm they appear in player-facing list and never leak stages,
   oracles, or sheet shapes across systems (SC-007).

✅ **Pass**: both systems listed at `GET /api/systems`.

### V2 — Illegal flow edits blocked (US1-3, FR-005)

1. With a campaign parked on stage `Scene` (create one in V4 first), try to delete/rename that stage.
2. Backoffice refuses the change until campaigns are moved off it.

✅ **Pass**: modification refused with occupancy explanation; flow intact for running campaign.

### V3 — Guided play loop (US2, FR-012..018)

Using frontend (`http://localhost:3000`) or raw API:

```bash
TOKEN=$(curl -s localhost:8080/api/auth/login -d '{"email":"player@example.com","password":"..."}' | jq -r .token)
curl -s localhost:8080/api/campaigns -X POST -H "Authorization: Bearer $TOKEN" \
     -d '{"gameSystemId":"<scene-sequel-id>"}'
```

1. New campaign lands on starting stage `Scene` with visible guidance ("Open your Scene").
2. Write a journal entry → stored against current stage.
3. Advance to `Sequel` → allowed; guidance updates ("Run Sequel").
4. Attempt `Scene → Act II` of the other system's shape / any non-edge target → refused with legal options.
5. Stop. Reopen later → same stage, journal, history restored exactly.

✅ **Pass**: all five behaviors observable without any external notebook/PDF (SC-006).

### V4 — Oracle scoping & consultation (US3+US4, FR-007..011)

1. In backoffice create "Generic Weather" (global) and "D&D-style Encounters" scoped to Act Ladder.
2. From a Scene-Sequel campaign, oracle listing shows Weather but NOT Encounters; from an Act Ladder
   campaign both appear.
3. Consult "Weather" repeatedly (≥100×) → results distributed proportionally to weights.
4. Create an empty table and consult it → friendly empty-table notice, no error state.
5. Save a consulted result to journal → entry references oracle title + result text.

✅ **Pass**: scoping matrix correct (US3 independent test); single weighted result per consult;
CI additionally asserts ±5 % over 10k seeded consultations (SC-004).

### V5 — System-shaped characters (US5, FR-021..025)

1. Give each demo system a distinct sheet structure (e.g., HP/spellSlots vs willpower/disciplines).
2. Under Scene-Sequel create a PC with conforming attributes → saved & displayed.
3. Submit missing required field / wrong-typed value → refused with field-level messages.
4. Add NPC with lighter required set → accepted where a PC would be rejected.
5. Edit a system structure removing a tracked attribute → affected characters flagged for review,
   still readable/editable, data untouched.

✅ **Pass**: cross-system sheets never mix; drift flagging visible in character views.

### V6 — Dice roller (US6, FR-026..029)

| Input | Expected |
|-------|----------|
| `1d20+5` | one die shown; total ∈ [6,25] |
| `2d6` | both dice shown + sum |
| `2d`, `d20x`, `0d6`, `1d0` | refused pre-roll, specific reason, no result |
| log action | notation, dice values, total, timestamp appended to journal |

Automated gate: 100 % valid inputs mathematically correct; 100 % invalid refused helpfully (SC-005).

## Automated Test Suites

```bash
# Backend pure domain/application (no framework boot)
docker compose exec php vendor/bin/phpunit --testsuite unit
# Doctrine/JSONB adapter round-trips
docker compose exec php vendor/bin/phpunit --testsuite integration
# Executable specifications in ubiquitous language
docker compose exec php vendor/bin/behat
# Static quality gates
docker compose exec php vendor/bin/phpstan analyse
# Frontend component + journey tests
docker compose exec frontend npm run test          # Vitest
docker compose exec frontend npm run test:e2e      # Playwright vs compose stack
```

All green = Constitution gates I–V demonstrably satisfied for this feature slice.

## Contract Drift Check

```bash
curl -s localhost:8080/api/docs.json > /tmp/runtime-openapi.json
```

Diff runtime document against `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`
(paths/schemas must match; CI fails on drift).

## Increment Validation: Admin Campaign-Flows Editor

Prerequisites: stack up, an admin account (`docker compose exec php bin/console app:create-admin`),
at least one authored system.

1. Sign in at `http://localhost:8080/admin/login` → backoffice loads.
2. Open **Game systems**: the list renders every row (previously crashed with
   "flowDefinition … can't be converted into a string" once data existed).
3. Open **Campaign flows** (new menu entry): systems listed; NEW/DELETE absent.
4. Edit a flow: add/remove stage rows, set guidance, pick starting stage, wire
   transitions via from/to selects, save → success flash.
5. Remove a stage currently occupied by a campaign → refusal naming the stage
   (FR-005), nothing persisted.
6. Point a transition at a not-yet-existing stage and save → domain validation
   error, no partial write.
7. `GET /admin/system` detail + oracles pages still render normally.

Automated: `tests/Integration/Rulesets/AdminGameFlowPagesTest.php` covers
authenticated index/edit round-trip; unit suite covers form mapping without
booting the kernel.
