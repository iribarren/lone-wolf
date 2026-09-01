# Tasks: Lone Wolf — Solo TTRPG Digital Assistant

**Input**: Design documents from `/specs/001-solo-ttrpg-assistant/`

**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/openapi.yaml ✅, quickstart.md ✅

**Tests**: Test tasks ARE included. The Constitution (Principle IV — Testing Discipline, NON-NEGOTIABLE) mandates pure-PHPUnit coverage for Domain/Application plus Behat executable specifications, and the spec defines measurable test gates (SC-004/SC-005/SC-008). Every story therefore ships tests-first unit/integration tasks and a closing Behat feature. Frontend slices include Vitest component tests; one Playwright smoke lands in Polish.

**Organization**: Tasks grouped by user story (spec.md priorities P1–P5) so each story is independently implementable and testable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Exact file paths included in every task

## Path Conventions

Web app (per plan.md): `backend/src/<Context>/{Domain,Application,Infrastructure}`, `backend/tests/{Unit,Integration}`, `backend/features/` (Behat), `frontend/src/{app,components,lib}`.

---

## Phase 1: Setup (Monorepo & Docker Foundation)

**Purpose**: Bootable two-stack monorepo matching plan.md Phase-1 outline

- [x] T001 Create monorepo layout with `/backend` and `/frontend` directories and root `README.md` documenting the two-stack split and contract-first (OpenAPI-only) communication rule
- [x] T002 Author `docker-compose.yml` at repo root with services `php`, `nginx`, `postgres`, `frontend` plus root `.env.dist` templates (no secrets committed)
- [x] T003 [P] Write `backend/Dockerfile`: php:8.3-fpm + Composer + pdo_pgsql/intl/zip/opcache extensions
- [x] T004 [P] Write `docker/nginx/default.conf`: FastCGI proxy to php service serving `backend/public`
- [x] T005 [P] Write `frontend/Dockerfile`: node:22 image running Next.js dev server
- [x] T006 [P] Add root `Makefile` with targets up/down/logs/test/lint/console/npm wrapping compose commands
- [x] T007 Verify `docker compose up --build` yields reachable nginx vhost and frontend placeholder; record expected output in `README.md`

**Checkpoint**: One-command stack boots; nothing implemented yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Framework cores, quality gates, auth, and API plumbing that ALL user stories depend on

**⚠️ CRITICAL**: No user story work begins until this phase completes

### Backend core (Symfony + Hexagon + Gates)

- [x] T010 Install Symfony 7.4 LTS skeleton into `backend/` (composer.json, bin/console, public/index.php, .env) wired to compose PostgreSQL
- [x] T011 [P] Create hexagonal bounded-context skeletons `backend/src/{Shared,Rulesets,Campaigns,Journal,Oracles,Characters,Dice,Identity}/{Domain,Application,Infrastructure}` each with a `README.md` stating its ubiquitous language and inward-only dependency rule
- [x] T012 Configure PHPStan level-max + phpstan/phpstan-strict-rules (enforces declare(strict_types=1), full typing) in `backend/phpstan.neon`; add `composer analyze` script
- [x] T013 Configure PHPUnit 11 multi-suite setup (`backend/phpunit.xml`): `unit` suite boots NO kernel, `integration` suite boots kernel; create `backend/tests/Unit/`, `backend/tests/Integration/`; add `composer test:unit`, `test:integration`
- [x] T014 [P] Install Behat + FriendsOfBehat/SymfonyExtension with API HTTP-client contexts; `backend/behat.yml`; empty smoke feature `backend/features/smoke.feature`
- [x] T015 [P] Install deptrac with layer rules per context (Domain ← Application ← Infrastructure; Domain may import nothing outward) in `backend/deptrac.yaml`; wire into `composer lint` chain
- [x] T016 Implement shared-kernel ports + prod adapters `ClockInterface`/`SystemClock`, `RandomSourceInterface`/`ProductionRandomSource` in `backend/src/Shared/Domain/` and `backend/src/Shared/Infrastructure/Time|Rng/` (Constitution IV: injectable time/randomness)
- [x] T017 Implement typed identifier VOs `GameSystemId`, `StageId`, `CampaignId`, `OracleId`, `CharacterId`, `JournalEntryId`, `UserId` in `backend/src/Shared/Domain/Identifier/` (data-model.md shared kernel)
- [x] T018 Configure Doctrine ORM connection + migration framework with `jsonb` DBAL type override registered in `backend/config/packages/doctrine.yaml` + `backend/src/Shared/Infrastructure/Persistence/Types/`; generate initial empty migration
- [x] T019 Implement Identity context: `User` aggregate (email, passwordHash, roles array), `UserRepositoryInterface` port + Doctrine adapter in `backend/src/Identity/{Domain,Application,Infrastructure}`
- [x] T020 Install lexik/jwt-authentication-bundle; wire JWT firewalls for `/api` and `/admin` (ROLE_ADMIN backoffice vs ROLE_PLAYER, FR-030); expose `POST /api/auth/register` + `POST /api/auth/login` controllers in `backend/src/Identity/Infrastructure/Api/` per contracts/openapi.yaml Auth paths
- [x] T021 Install API Platform; enable OpenAPI docs endpoint `/api/docs.json`, RFC 7807 error format, pagination defaults; add health route `GET /api/health` returning `{"status":"ok"}` in `backend/src/Shared/Infrastructure/Api/HealthController.php`
- [x] T022 Create bootstrap admin seeder `backend/src/Identity/Infrastructure/Console/CreateAdminCommand.php` (command `app:create-admin`) writing a ROLE_ADMIN account from env vars

### Frontend core (Next.js + contract-first client)

- [x] T023 Scaffold Next.js App Router project with TypeScript `strict` mode, ESLint, base layout in `frontend/` (package.json/tsconfig/next.config.ts) running inside its container
- [x] T024 Build contract-first client pipeline: `frontend/scripts/generate-api-client.sh` downloads backend `/api/docs.json` → `openapi-typescript` emits `frontend/src/lib/api/schema.gen.ts`; hand-written typed fetch wrapper `frontend/src/lib/api/client.ts` (raw URLs prohibited anywhere else, Constitution V)
- [x] T025 [P] Wire TanStack Query provider, JWT bearer attach + token storage helpers in `frontend/src/lib/auth.ts` and `frontend/src/lib/hooks/useApiClient.ts`

**Checkpoint**: Foundation ready — user stories can start (and US1/US2 may proceed in parallel afterwards).

---

## Phase 3: User Story 1 — Define Game Systems and Their Campaign Flows (Priority: P1) 🎯 MVP

**Goal**: Admins author game systems owning exactly one Campaign Flow (named stages, legal transitions, designated starting stage) and optional sheet structures; active systems appear to players; occupied stages cannot be removed/renamed.

**Independent Test**: Via backoffice, create a system with a multi-stage flow → it appears in `GET /api/systems`; attempting to remove a stage occupied by a campaign (guard port) is refused; deactivating hides it from new campaigns while existing ones stay playable.

### Tests for User Story 1 (write FIRST, ensure they FAIL)

- [x] T026 [P] [US1] Unit tests for `FlowDefinition` invariants (≥2 stages, unique names, exactly-one starting stage, transitions reference existing stages) in `backend/tests/Unit/Rulesets/FlowDefinitionTest.php`
- [x] T027 [P] [US1] Unit tests for `SheetStructure`/`FieldDefinition` VOs (unique keys, select-options required, PC/NPC requirement flags, version stamp) in `backend/tests/Unit/Rulesets/SheetStructureTest.php`
- [x] T028 [P] [US1] Unit tests for `GameSystem` activate/deactivate semantics (deactivation never mutates playable campaigns, FR-006) in `backend/tests/Unit/Rulesets/GameSystemStatusTest.php`
- [x] T029 [P] [US1] Integration test: flow modification blocked while `StageOccupancyChecker` reports occupation (FR-005) and optimistic-lock supersede conflict surfaces in `backend/tests/Integration/Rulesets/FlowModificationGuardTest.php`

### Implementation for User Story 1

- [x] T030 [US1] Implement Rulesets Domain model: `FlowStage`, `FlowTransition`, `FlowDefinition`, `FieldDefinition`, `SheetStructure`, `GameSystem` aggregate (name/description/status/activate()/deactivate(), owns FlowDefinition + SheetStructure) in `backend/src/Rulesets/Domain/` — pure PHP, native types only
- [x] T031 [US1] Define Application ports `RulesetRepositoryInterface`, `StageOccupancyCheckerInterface` and read query `ListAvailableSystems` in `backend/src/Rulesets/Application/`
- [x] T032 [US1] Implement handlers `CreateGameSystem`, `UpdateFlowDefinition` (runs occupancy guard, refuses with explanation), `UpdateSheetStructure` (bumps version), `SetSystemStatus` in `backend/src/Rulesets/Application/`
- [x] T033 [US1] Doctrine persistence for Rulesets: XML/attribute mappings, repositories implementing ports, `jsonb` columns for flow_definition + sheet_structure, optimistic-lock `version` column; migration in `backend/migrations/`
- [x] T034 [US1] EasyAdmin backoffice CRUD for systems incl. stage/transition editor enforcing FR-002..FR-005 messages and sheet-structure field editor in `backend/src/Rulesets/Infrastructure/Admin/SystemCrudController.php`. Configure the optimistic-lock version field and catch `OptimisticLockException` to render a "your changes were superseded — review the current version" flash message (Edge Case §8)
- [x] T035 [US1] Expose API Platform resource `GET /api/systems` (active-only summaries with startingStage + openingGuidance, per contracts SystemSummary) in `backend/src/Rulesets/Infrastructure/Api/SystemResource.php`
- [x] T036 [US1] Bind Rulesets' `StageOccupancyCheckerInterface` to an in-memory null checker in `backend/src/Rulesets/Infrastructure/Persistence/InMemoryStageOccupancyChecker.php` (zero campaigns can exist before US2; the real Doctrine adapter replaces this stub in T047)
- [x] T037 [US1] Behat feature: admin authors Scene-Sequel + Act Ladder systems, both appear in player-facing list; occupied-stage edit refused (quickstart V1/V2) in `backend/features/rulesets/author_system_flow.feature`

**Checkpoint**: US1 independently functional — admin content pipeline works end-to-end.

---

## Phase 4: User Story 2 — Run a Guided Solo Campaign (Priority: P2)

**Goal**: Players pick a system, create a campaign landing on the starting stage with pacing guidance, journal against the current stage, advance only along legal transitions (illegal refusals explain alternatives), stop/resume with exact state restoration.

**Independent Test**: With one configured system: create campaign → opening prompt shown → write entry → advance through a full stage cycle → close & reopen → same stage + journal intact (quickstart V3).

### Tests for User Story 2 (write FIRST, ensure they FAIL)

- [x] T039 [P] [US2] Unit tests for `FlowEngine` state machine: `legalNextStages()`, `assertCanAdvance()` throwing exception carrying legal alternatives (FR-016), terminal-stage conclude-guidance (US2-5) in `backend/tests/Unit/Campaigns/FlowEngineTest.php`
- [x] T040 [P] [US2] Unit tests for handlers: StartCampaign positions on designated starting stage (FR-013) and refuses inactive systems (FR-012), AdvanceStage refusal payload shape, AppendNarrativeEntry stamps current stage (FR-015) in `backend/tests/Unit/Campaigns/HandlersTest.php`
- [x] T041 [P] [US2] Integration test: campaign + journal persistence round-trip proving resume-exactly (FR-018), owner-scoped reads (FR-019), irreversible delete cascades in `backend/tests/Integration/Campaigns/PersistenceResumeTest.php`

### Implementation for User Story 2

- [x] T043 [US2] Implement Campaigns Domain: `Campaign` aggregate (playerId, gameSystemId immutable, StagePosition), `Guidance`, `IllegalStageTransitionException`, graph-driven `FlowEngine` service in `backend/src/Campaigns/Domain/`
- [x] T044 [US2] Implement Journal Domain: `JournalEntry` aggregate (kind narrative|oracle_result|dice_roll, stageId + denormalized stageName snapshot, nullable snapshots) in `backend/src/Journal/Domain/`
- [x] T045 [US2] Define ports `CampaignRepositoryInterface`, `JournalEntryRepositoryInterface`, `FlowDefinitionProviderInterface` in `backend/src/Campaigns/Application/` and `backend/src/Journal/Application/`
- [x] T046 [US2] Implement handlers: `StartCampaign` (validates the bound system is active, refusing otherwise — FR-012), `AdvanceStage` (422 refusal listing legal alternatives), `AppendNarrativeEntry`, `GetCampaignState` (guidance + suggestedActions), `ListJournalEntries` (keyset-paginated, stage-groupable), `DeleteCampaign` (requires confirm flag, hard delete) in `backend/src/Campaigns/Application/` + `backend/src/Journal/Application/`
- [x] T047 [US2] Doctrine persistence for Campaigns + Journal: mappings, repositories, FK cascades, covering index `(campaign_id, created_at DESC)` (SC-008); migration. Includes `DoctrineStageOccupancyChecker` answering Rulesets' FR-005 port, replacing the T036 stub
- [x] T048 [US2] Expose API Platform endpoints per contract: `GET/POST /api/campaigns`, `GET /api/campaigns/{id}`, `POST /api/campaigns/{id}/advance`, `GET/POST /api/campaigns/{id}/journal`, `DELETE /api/campaigns/{id}?confirm=true` in `backend/src/Campaigns/Infrastructure/Api/` + `backend/src/Journal/Infrastructure/Api/`
- [x] T049 [US2] Enforce per-player ownership security voters/query filters on every campaign/journal operation (FR-019/FR-030) in `backend/src/Campaigns/Infrastructure/Security/CampaignOwnershipVoter.php`
- [x] T050 [US2] Frontend system-picker + campaign creation flow posting to generated client in `frontend/src/app/(play)/campaigns/new/page.tsx`
- [x] T051 [US2] Frontend GM console (structure-aware, not flat journal): current-stage guidance card, suggested-action buttons derived from legalTransitions, refusal feedback banner showing legal options, exact-resume on open in `frontend/src/components/campaign/StagePanel.tsx`, `AdvanceActions.tsx` + `frontend/src/app/(play)/campaigns/[id]/page.tsx`. Include a CampaignSettings section with a delete control requiring typed confirmation and an irreversibility notice, calling `DELETE /api/campaigns/{id}?confirm=true` (FR-020)
- [x] T052 [US2] Frontend stage-grouped chronological journal timeline + narrative composer keyed to current stage in `frontend/src/components/journal/JournalTimeline.tsx`, `EntryComposer.tsx`
- [x] T053 [P] [US2] Vitest component tests for StagePanel/AdvanceActions states (guidance render, advance enabled set, refusal feedback, terminal conclude) in `frontend/tests/components/campaign/StagePanel.test.tsx`
- [x] T054 [US2] Behat guided-play feature executing quickstart V3 incl. illegal-move refusal body + resume assertions in `backend/features/campaigns/guided_play.feature`

**Checkpoint**: US1 + US2 = playable MVP loop (create system → play guided campaign → journal).

---

## Phase 5: User Story 3 — Author Oracles Scoped to a System or Global (Priority: P3)

**Goal**: Admins build titled random tables with weighted textual entries; each oracle scopes to exactly one system OR globally; visibility resolves per campaign's system.

**Independent Test**: Create one global + one system-specific table → verify presence/absence across two different systems' listings (US3 independent test).

**Depends on**: US1 (systems must exist to scope against)

### Tests for User Story 3 (write FIRST, ensure they FAIL)

- [X] X056 [P] [US3] Unit tests for `OracleScope` strategy (GlobalScope/SystemScope) visibility predicate matrix + `Oracle` aggregate weight>0 invariants (FR-007) in `backend/tests/Unit/Oracles/OracleScopeTest.php` + `backend/tests/Unit/Oracles/OracleAggregateTest.php`
- [X] X057 [P] [US3] Integration test: partial unique index enforces system-scope integrity and scoped listing query returns global ∪ own-system rows (FR-009 predicate) in `backend/tests/Integration/Oracles/PersistenceScopingTest.php`

### Implementation for User Story 3

- [X] X059 [US3] Implement Oracles Domain: `OracleScope` VO (`GlobalScope` | `SystemScope(GameSystemId)`), `OracleEntry` (text, weight int>0), `Oracle` aggregate with entry management + `isAvailableTo(GameSystemId)` in `backend/src/Oracles/Domain/`
- [X] X060 [US3] Define `OracleRepositoryInterface` port + handlers `CreateOracle`, `UpdateOracle` (reweight/edit entries), `ListOraclesVisibleToSystem` in `backend/src/Oracles/Application/`
- [X] X061 [US3] Doctrine mapping: `scope_type` discriminator column + `scope_system_id` with partial unique index `WHERE scope_type='system'`; migration in `backend/migrations/`
- [X] X062 [US3] EasyAdmin Oracle CRUD with scoping picker and weighted-entries grid in `backend/src/Oracles/Infrastructure/Admin/OracleCrudController.php`
- [X] T063 [US3] Behat authoring feature: entries authored through the backoffice, a global table visible to every system, a scoped table only to its own, and a system refused a second scoped table (US3 scenarios 1–3 + FR-008) in `backend/features/oracles/authoring.feature`

**Checkpoint**: US1–US3 deliver the complete admin authoring surface.

---

## Phase 6: User Story 4 — Consult Oracles During Play (Priority: P3)

**Goal**: During play, players see only applicable tables, consult for exactly one weighted-random result, save it to the journal with interpretation; empty tables fail friendly.

**Independent Test**: From a campaign, browse scoped list → repeated consults return single proportional results → saved result appears in journal referencing oracle name + text.

**Depends on**: US2 (campaign context), US3 (tables exist)

### Tests for User Story 4 (write FIRST, ensure they FAIL)

- [x] T065 [P] [US4] Unit tests for `WeightedOracleSelector`: distribution within ±5% over 10,000 seeded-RNG consultations (SC-004), `empty_table` outcome for zero entries (FR-011), deterministic reproducibility in `backend/tests/Unit/Oracles/WeightedOracleSelectorTest.php`
- [x] T066 [P] [US4] Integration test: consult endpoint visibility per campaign system + unavailable-oracle graceful outcome (retired table edge case) in `backend/tests/Integration/Oracles/ConsultVisibilityTest.php`

### Implementation for User Story 4

- [x] T068 [US4] Implement Domain: `ConsultationOutcome` result object (`selected` | `empty_table` | `unavailable`) and `WeightedOracleSelector` (cumulative-weight pick via injected RandomSource) in `backend/src/Oracles/Domain/`
- [x] T069 [US4] Implement handlers: `ConsultOracleHandler` (visibility check against campaign's system) and `SaveConsultationToJournalHandler` creating `oracle_result` entry with denormalized `{oracleTitle, resultText}` snapshot in `backend/src/Oracles/Application/` + `backend/src/Journal/Application/`
- [x] T070 [US4] Expose endpoints `GET /api/campaigns/{id}/oracles` and `POST /api/campaigns/{id}/oracles/{oracleId}/consult` per contract schemas OracleSummary/ConsultationOutcome in `backend/src/Oracles/Infrastructure/Api/`
- [x] T071 [US4] Frontend floating Oracle drawer widget during play: scoped table list, consult action rendering single result, save-to-journal composer with interpretation field, friendly empty-table notice in `frontend/src/components/oracles/OracleDrawer.tsx`
- [x] T072 [P] [US4] Vitest component test for OracleDrawer states (list filtering, selected-result render, empty-table notice) in `frontend/tests/components/oracles/OracleDrawer.test.tsx`
- [x] T073 [US4] Behat consultation journey: browse scoped, single weighted result, save to journal (US4 scenarios 1–3) in `backend/features/oracles/consultation.feature`

**Checkpoint**: Improvisation loop live — uncertainty converts to journal-recorded story in-app.

---

## Phase 7: User Story 5 — Track Characters with System-Shaped Sheets (Priority: P4)

**Goal**: PCs/NPCs per campaign whose attributes conform to their system's SheetStructure (JSONB), field-level rejection of mismatches, lighter NPC requirements, drift-flagged review instead of silent alteration.

**Independent Test**: Two systems with different sheet shapes: each accepts conforming attributes, rejects mismatched ones with field-level guidance (US5 independent test).

**Depends on**: US1 (sheet structures authored), US2 (campaign to belong to)

### Tests for User Story 5 (write FIRST, ensure they FAIL)

- [x] T075 [P] [US5] Unit tests for `AttributeValidator`: PC vs NPC required-set enforcement (FR-024), type/select-option violations produce per-field messages (FR-023), unknown-key rejection in `backend/tests/Unit/Characters/AttributeValidatorTest.php`
- [x] T076 [P] [US5] Integration test: JSONB attributes round-trip + `DriftDetector` flags characters when stored data fails bumped structure version, leaving data untouched/readable (FR-025) in `backend/tests/Integration/Characters/DriftFlaggingTest.php`

### Implementation for User Story 5

- [x] T078 [US5] Implement Characters Domain: `Character` aggregate (kind pc|npc, name, `AttributesMap` JSONB VO, validatedStructureVersion, reviewStatus + driftIssues), `AttributeValidator`, `DriftDetector` in `backend/src/Characters/Domain/`
- [x] T079 [US5] Define `CharacterRepositoryInterface` port + handlers `CreateCharacter`, `UpdateCharacter` (revalidates against CURRENT structure), `ListCharacters` (returns structure metadata for rendering) in `backend/src/Characters/Application/`
- [x] T080 [US5] Doctrine persistence: `jsonb` attributes column, FK to campaign with cascade, migration
- [x] T081 [US5] Expose endpoints `GET/POST /api/campaigns/{id}/characters` and `PATCH /api/characters/{characterId}` emitting `SheetValidationProblem` (violations[{field,message}]) per contract in `backend/src/Characters/Infrastructure/Api/`
- [x] T082 [US5] Frontend dynamic character panel rendering sheets from returned structure metadata (no hardcoded fields), violation feedback inline, flagged-for-review badge in `frontend/src/components/characters/CharacterPanel.tsx`
- [x] T083 [P] [US5] Vitest component test for CharacterPanel render-per-shape + violation display in `frontend/tests/components/characters/CharacterPanel.test.tsx`
- [x] T084 [US5] Behat feature: two systems/different shapes — conforming accepted, missing/wrong-typed rejected field-level, NPC lighter set passes where PC fails (US5 scenarios 1–3) in `backend/features/characters/sheets.feature`

**Checkpoint**: Mechanical truth lives beside narrative; multi-system claim credible.

---

## Phase 8: User Story 6 — Roll Dice with Standard Notation (Priority: P5)

**Goal**: Parse `NdM±K` strictly, refuse pathological input pre-roll with specific reasons, show every die + modified total, optionally log rolls to the journal.

**Independent Test**: Submit series of valid/invalid notations → correct results/helpful refusals; logged roll appears as journal record (US6 independent test).

**Depends on**: US2 (only for the log-to-journal variant; standalone roll endpoint is foundation-independent)

### Tests for User Story 6 (write FIRST, ensure they FAIL)

- [x] T086 [P] [US6] Unit tests for `DiceNotationParser` matrix: valid `2d6`,`1d20+5`,`3d6-2`; malformed `2d`,`d20x` → `malformed`; `0d6` → `invalid_count`; `1d0` → `invalid_faces`; overflow → `out_of_bounds` (bounds N∈[1,50], M∈[2,1000], K∈[-10000,10000]) in `backend/tests/Unit/Dice/DiceNotationParserTest.php`
- [x] T087 [P] [US6] Unit tests for `DiceRoller` over seeded batch: diceValues length/value ranges and Σ±modifier totals mathematically correct, 100%-valid/100%-refused gate (SC-005) in `backend/tests/Unit/Dice/DiceRollerTest.php`

### Implementation for User Story 6

- [x] T089 [US6] Implement Dice Domain: `DiceNotation` value + parser with typed failure reasons, `DiceRoll` VO (diceValues, modifier, total, rolledAt via Clock), `DiceRoller` (RandomSource-injected) in `backend/src/Dice/Domain/`
- [x] T090 [US6] Implement handlers `RollDiceHandler` and `RollAndLogHandler` (creates `dice_roll` journal entry with `{notation, diceValues, modifier, total}` snapshot, FR-029) in `backend/src/Dice/Application/`
- [x] T091 [US6] Expose endpoints `POST /api/dice/roll` (200 result / 422 DiceNotationProblem) and `POST /api/campaigns/{id}/rolls` (201 roll + journalEntry) per contract in `backend/src/Dice/Infrastructure/Api/`
- [x] T092 [US6] Frontend floating dice widget: notation input, per-die chips + modified-total display, invalid-reason toast (never a fake result), log-to-journal action in `frontend/src/components/dice/DiceRollerWidget.tsx`
- [x] T093 [P] [US6] Vitest component test for DiceRollerWidget (result render, error states) in `frontend/tests/components/dice/DiceRollerWidget.test.tsx`
- [x] T094 [US6] Behat dice feature mirroring quickstart V6 table exactly in `backend/features/dice/notation.feature`

**Checkpoint**: All six user stories independently functional.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Gates, performance evidence, docs parity (Constitution VI)

- [x] T096 [P] Documentation parity pass: update root `README.md` + `docs/` architecture notes (contexts diagram, flow-engine explanation) reflecting everything delivered this change set
- [x] T097 [P] Add contract-drift check script `scripts/check-contract.sh`: download runtime `/api/docs.json`, diff paths/schemas against `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`, exit non-zero on drift (Constitution V gate)
- [x] T098 [P] Performance evidence fixture: seeder generating 500-entry journal + scripted timing assertion of latest-view < 2 s (SC-008) in `backend/src/Journal/Infrastructure/Console/SeedLargeJournalCommand.php` + `scripts/check-journal-performance.sh`
- [x] T099 [P] Extend `app:create-admin` into `app:seed:demo` providing quickstart demo content (Scene-Sequel + Act Ladder + Freeform Sandbox systems — the sandbox being a single terminal stage exercising dead-end guidance, satisfying SC-007's three-system requirement — plus global + scoped oracles) in `backend/src/Shared/Infrastructure/Console/`
- [x] T100 Security hardening sweep: verify ownership voter coverage on ALL player endpoints, JWT expiry/clock-skew config, `/admin` restricted to ROLE_ADMIN, secrets only via env (FR-030/FR-031)
- [x] T101 Playwright E2E smoke mirroring quickstart happy-path (login → new campaign → guidance → advance → journal entry visible) in `frontend/tests/e2e/play.spec.ts` + `npm run test:e2e` script
- [x] T102 Execute full `specs/001-solo-ttrpg-assistant/quickstart.md` walkthrough (V1–V6) fixing any gaps found; record results in PR description. Verify SC-007 explicitly: all three seeded systems run simultaneously with no cross-system leakage of stages, sheets, or oracles

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none — starts immediately
- **Foundational (Phase 2)**: depends on Setup; **BLOCKS all user stories**
- **US1 (Phase 3)**: after Foundational — no story dependencies
- **US2 (Phase 4)**: after Foundational — needs a system to play, supplied by US1's backoffice (or seed); engine/persistence work itself is US1-independent
- **US3 (Phase 5)**: after US1 (scopes reference systems)
- **US4 (Phase 6)**: after US2 + US3
- **US5 (Phase 7)**: after US1 (structures) + US2 (campaign)
- **US6 (Phase 8)**: after Foundational for rolling; after US2 for the logged-roll endpoint
- **Polish (Phase 9)**: after desired stories complete

### User Story Dependencies

```text
Phase 1 ──▶ Phase 2 ──┬──▶ US1 (P1) ──┬──▶ US3 (P3) ──┐
                      │               │               ├──▶ US4 (P3)
                      ├──▶ US2 (P2) ──┴───────────────┤
                      │               └──▶ US5 (P4)   │
                      └──▶ US6 (P5, roll half independent) ◀── US2 for log variant
All stories ──▶ Polish (Phase 9)
```

### Within Each Story

Tests first (must FAIL) → Domain → ports/handlers (Application) → Infrastructure persistence → API/EasyAdmin surface → frontend slice → Behat feature closes the story.

### Parallel Opportunities

- Phase 1: T003–T006 fully parallel
- Phase 2: T011/T012/T013/T014/T015 parallel; frontend trio T023–T025 parallel with backend tasks
- Within stories: all `[P]` test tasks before implementation; Domain files parallelizable
- Across stories: US1 ∥ US2 after Foundational; US3 ∥ US5 ∥ US6 after their prerequisites; backend and frontend tracks always parallelizable

---

## Parallel Example: User Story 2

```bash
# Launch all US2 test tasks together:
Task: "Unit FlowEngine tests in backend/tests/Unit/Campaigns/FlowEngineTest.php"
Task: "Unit handler tests in backend/tests/Unit/Campaigns/HandlersTest.php"
Task: "Integration persistence/resume tests in backend/tests/Integration/Campaigns/PersistenceResumeTest.php"

# Then Domain aggregates in parallel (different contexts):
Task: "Campaign aggregate + FlowEngine in backend/src/Campaigns/Domain/"
Task: "JournalEntry aggregate in backend/src/Journal/Domain/"
```

---

## Implementation Strategy

### MVP First (US1 only)

1. Phases 1–2 complete → foundation green (`composer analyze`, deptrac, empty suites pass)
2. Deliver Phase 3 (US1) → validate: backoffice authors system, `GET /api/systems` lists it
3. STOP and VALIDATE via T037 Behat feature

### Minimal Playable Product (US1 + US2)

4. Deliver Phase 4 → full guided loop: author system → create campaign → prompts → journal → resume
5. Validate with quickstart V1–V3; this is the demo-able core promise (automated GM)

### Incremental Delivery

- +US3/US4 → improvisation loop (oracle authoring + in-play consultation)
- +US5 → credible multi-system character sheets
- +US6 → mechanical glue (dice) with journal records
- Each increment keeps prior stories green; contract additions extend `contracts/openapi.yaml` in the same change set

### Solo-Developer Strategy

Execute strictly in priority order US1→US2→US3→US4→US5→US6; use [P] markers to batch file-independent work per session; commit after each checkpoint.

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Every story ends with a passing Behat feature in ubiquitous language (Constitution IV)
- Verify tests FAIL before implementing them away
- Commit after each task or logical group; stop at any checkpoint to validate the story independently
- Breaking API changes require updating `contracts/openapi.yaml` + regeneration in the same change set (Constitution V)
