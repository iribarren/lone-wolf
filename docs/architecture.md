# Architecture Notes

Feature: `001-solo-ttrpg-assistant` · Last parity pass: 2026-08-24 (Phase 9, T096)

These notes describe the system **as delivered** through Phase 8 (all six user
stories). Governance lives in `.specify/memory/constitution.md`; endpoint-level
detail lives in `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`.

## The 30-second tour

Two decoupled stacks talk only through an OpenAPI-documented REST contract:

```text
┌─────────────────────┐   OpenAPI contract    ┌──────────────────────────────┐
│ frontend/ (Next.js) │ ────────────────────▶ │ backend/ (Symfony 7.4 LTS)   │
│ player app          │   generated client    │ API + EasyAdmin backoffice   │
└─────────────────────┘   (never hand-edited) └──────────────┬───────────────┘
                                                             │ Doctrine
                                                      ┌──────▼───────┐
                                                      │ PostgreSQL   │
                                                      └──────────────┘
```

- **Player app** (`http://localhost:3000`): sign in, start a campaign on a
  system, follow stage guidance, journal, consult oracles, manage characters,
  roll dice.
- **Admin backoffice** (`http://localhost:8080/admin`, `ROLE_ADMIN` only,
  sign in at `/admin/login`): authors game systems + campaign flows + sheet
  structures, scoped oracles.
- **API** (`http://localhost:8080/api`): the single integration surface;
  errors are RFC 7807 `application/problem+json`.

## Bounded contexts (Constitution II)

No global `Entity/`/`Repository/`/`Controller/` folders exist. Each context
owns its domain model, its language, its ports and its persistence mapping:

```text
backend/src/
├── Shared/       shared kernel: typed UUID identifiers, Clock + RandomSource ports,
│                 current-user port, jsonb DBAL type, health endpoint, demo seeder
├── Rulesets/     GameSystem aggregate = flow definition (+ optional sheet structure).
│                 Admin-authored; activation never mutates playable campaigns (FR-006)
├── Campaigns/    Campaign aggregate + FlowEngine state machine + guidance views
├── Journal/      append-only entries stamped with the stage they were written on;
│                 keyset-paginated newest-first reads (SC-008)
├── Oracles/      weighted tables scoped globally or to one system (scope strategy);
│                 weighted random selection via injected RandomSource
├── Characters/   PC/NPC sheets validated against the owning system's structure
│                 (jsonb attributes); drift is flagged, never silently altered
└── Dice/         strict NdM±K notation parser + RandomSource-injected roller
```

Cross-context collaboration happens only through context-owned ports or shared
identifiers — e.g. Campaigns reads flows through
`FlowDefinitionProviderInterface`; Journal never imports Campaigns' domain.

## Hexagonal layering (Constitution I)

```text
        ┌────────────────────────────────────────────┐
        │ Infrastructure                              │
        │  Doctrine repos · API Platform resources ·  │
        │  EasyAdmin CRUDs · console commands         │
        │     │ implements ▼        ▲ calls           │
        │  Application: handlers/use cases + PORTS   │
        │     │ orchestrates ▼                        │
        │  Domain: pure PHP 8.3 aggregates, VOs,     │
        │  services (FlowEngine, WeightedOracle…),   │
        │  zero framework imports                    │
        └────────────────────────────────────────────┘
```

Dependencies point inward only. `deptrac` (`backend/deptrac.yaml`) enforces
this mechanically as part of `composer lint`; PHPStan at max level with strict
rules enforces `declare(strict_types=1)` and full typing (Principle III).

## The Flow Engine (how pacing works)

Acts/Scenes/Beats are **not** hard-coded concepts — they are just stage names
in a data-driven graph authored per game system:

1. A `FlowDefinition` (Rulesets) declares named stages with per-stage guidance,
   legal transitions, and exactly one starting stage (≥2 stages; terminal
   stages simply have no outgoing transitions — "dead ends").
2. `Campaign::start` positions a new campaign on the system's designated
   starting stage (FR-013) after checking the system is active (FR-012).
3. `FlowEngine::legalNextStages(current)` powers the suggested-action buttons
   in the GM console; `assertCanAdvance()` throws an exception whose payload
   lists the legal alternatives — surfaced verbatim in the refusal banner
   (FR-016). Terminal stages return conclude-style guidance instead.
4. Every entry written while parked on a stage is stamped with that stage's
   name (denormalized snapshot survives renames, FR-015); stopping/resuming
   restores stage + journal exactly from persistence (FR-018).
5. Stage changes are always player-confirmed — the engine advises, it never
   moves the campaign by itself.

## Contract-first pipeline (Constitution V)

```text
contracts/openapi.yaml ──(authored first)──▶ API Platform implementation
        │                                            │
        │                                  runtime /api/docs.json
        ▼                                            ▼
frontend openapi-typescript client ◀── scripts/check-contract.sh (drift gate)
```

Hand-written code may only call the generated client (`frontend/src/lib/api/`);
raw URLs elsewhere are prohibited. The drift check script diffs runtime paths
and schemas against the canonical contract and fails loudly.

## Persistence highlights

- PostgreSQL via Doctrine; `jsonb` columns carry flow definitions, sheet
  structures, character attributes, oracle scopes (partial unique index keeps
  system-scoping 1:1) and journal snapshots.
- Journal reads use keyset pagination over `(campaign_id, created_at DESC, id)`
  so a 500-entry history stays fast — asserted by
  `scripts/check-journal-performance.sh` (<2 s, SC-008).
- Game systems carry an optimistic-lock `version`; superseded backoffice edits
  render a "your changes were superseded" flash instead of clobbering.
- Campaign deletion cascades to journal + characters at the storage level
  (irreversible, requires typed confirmation, FR-020).

## Security model (FR-030/FR-031)

- JWT bearer auth (`lexik/jwtAuthenticationBundle`): one-hour tokens with a
  60 s clock-skew allowance (`token_ttl`/`clock_skew`, pinned in config);
  `/api` is stateless JWT, registration always yields `ROLE_PLAYER`.
- Backoffice: the `admin` firewall is a browser session — `/admin/login`
  renders the EasyAdmin sign-in form (CSRF-protected), successful logins
  land on `/admin`, and `/admin/logout` invalidates the session. The
  backoffice account is provisioned by `app:create-admin`.
- Ownership: every campaign-scoped operation is gated by the `CAMPAIGN_OWNER`
  voter expression; unknown and foreign campaigns are indistinguishable (404).
- Secrets enter only through env vars (`.env.dist` holds placeholders).

## Testing discipline (Constitution IV)

| Suite | Boots kernel? | What it proves |
|-------|---------------|----------------|
| PHPUnit `unit` | no | Domain/Application invariants, seeded-RNG statistics |
| PHPUnit `integration` | yes | Doctrine adapters, jsonb round-trips, scoping SQL |
| Behat features | yes | US journeys end-to-end in ubiquitous language |
| Vitest | n/a | Frontend component states |
| Playwright smoke | n/a (full stack up) | Quickstart happy path |

Time and randomness are injected ports (`ClockInterface`,
`RandomSourceInterface`), keeping probabilistic tests deterministic.
