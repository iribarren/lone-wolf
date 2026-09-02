# Tasks: Lone Wolf — Solo TTRPG Digital Assistant

**Input**: Design documents from `/specs/001-solo-ttrpg-assistant/`

**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/openapi.yaml ✅, quickstart.md ✅

**Tests**: Test tasks ARE included. The Constitution (Principle IV — Testing Discipline, NON-NEGOTIABLE) mandates pure-PHPUnit coverage for Domain/Application plus Behat executable specifications, and the spec defines measurable test gates (SC-004/SC-005/SC-008). Every story therefore ships tests-first unit/integration tasks and a closing Behat feature. Frontend slices include Vitest component tests; one Playwright smoke lands in Polish.

**Organization**: Tasks grouped by user story (spec.md priorities P1–P5) so each story is independently implementable and testable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Exact file paths included in every task

## Task-id gaps (do not re-audit)

Ids run T001-T102 but only 89 tasks exist. Thirteen numbers were never issued — a
generation artifact present since the initial `tasks.md`, not lost or deleted work:

> T008, T009, T038, T042, T055, T058, T064, T067, T074, T077, T085, T088, T095

So task completeness cannot be checked by walking the id sequence. Check it with
`scripts/check-task-integrity.sh`, which verifies id shape, id uniqueness, and that every
completed task's cited files exist on disk.

Ids T056-T063 briefly appeared as `X056`-`X063`: commit `211c3e1` renamed them while
flipping them to `[X]` in the same diff, hiding user story US3 from every id-based grep.
The same commit deleted `backend/features/oracles/author_oracle_visibility.feature`, the
deliverable T063 claimed. Both are repaired; the commits stay in the log as evidence.

Tasks T103 and up are not part of that original generation: they are **converged increment
work** (Phase 10), appended by `/speckit-converge` for eight commits of admin-backoffice work
that shipped without a task. The ids run on from T102 unbroken — the phase heading, not a
numbering gap, marks where the original plan ends.

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
- [x] T013 Configure PHPUnit 11 multi-suite setup (`backend/phpunit.dist.xml`): `unit` suite boots NO kernel, `integration` suite boots kernel; create `backend/tests/Unit/`, `backend/tests/Integration/`; add `composer test:unit`, `test:integration`
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

- [X] T056 [P] [US3] Unit tests for `OracleScope` strategy (GlobalScope/SystemScope) visibility predicate matrix + `Oracle` aggregate weight>0 invariants (FR-007) in `backend/tests/Unit/Oracles/OracleScopeTest.php` + `backend/tests/Unit/Oracles/OracleAggregateTest.php`
- [X] T057 [P] [US3] Integration test: partial unique index enforces system-scope integrity and scoped listing query returns global ∪ own-system rows (FR-009 predicate) in `backend/tests/Integration/Oracles/PersistenceScopingTest.php`

### Implementation for User Story 3

- [X] T059 [US3] Implement Oracles Domain: `OracleScope` VO (`GlobalScope` | `SystemScope(GameSystemId)`), `OracleEntry` (text, weight int>0), `Oracle` aggregate with entry management + `isAvailableTo(GameSystemId)` in `backend/src/Oracles/Domain/`
- [X] T060 [US3] Define `OracleRepositoryInterface` port + handlers `CreateOracle`, `UpdateOracle` (reweight/edit entries), `ListOraclesVisibleToSystem` in `backend/src/Oracles/Application/`
- [X] T061 [US3] Doctrine mapping: `scope_type` discriminator column + `scope_system_id` with partial unique index `WHERE scope_type='system'`; migration in `backend/migrations/`
- [X] T062 [US3] EasyAdmin Oracle CRUD with scoping picker and weighted-entries grid in `backend/src/Oracles/Infrastructure/Admin/OracleCrudController.php`
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

## Phase 10: Convergence — Admin Backoffice Increment

**Purpose**: Bring the out-of-band admin-backoffice increment into the ledger it was built outside of

Everything from `T103` on is **converged increment work**, appended by `/speckit-converge`, not
part of the original T001–T102 generation. The numbering runs on from T102 without a gap; the
phase boundary is the discontinuity. Eight commits shipped between `1511584` and this phase
without a task — the admin sign-in form, the `/admin/system` index-crash fix and the dedicated
campaign-flows editor — and their design artifacts (`research.md` R11, the `data-model.md`
increment section, the `quickstart.md` validation section and
`specs/001-solo-ttrpg-assistant/contracts/admin-backoffice.md`) were retro-fitted by hand while
`tasks.md` still reported 89/89. Because the work was never gated, it shipped three critical
regressions — audit A1, A2 and A3 — which are logged here as their own tasks rather than folded
into the tasks that caused them.

Each task names the commit that delivered it. Completion here was verified twice: every cited
path was checked on disk, and every user-facing claim was re-driven in a browser (Playwright
against the running stack) — the increment is the exact case where "marked complete" and "actually
works" came apart.

### Delivered work converged from commits (US1)

- [x] T103 [US1] Failing integration spec for backoffice session login — unauthenticated redirect, bad credentials, admin lands on the dashboard, ROLE_PLAYER refused, logout ends the session — in `backend/tests/Integration/Identity/AdminBackofficeLoginTest.php` (FR-030) — commit `d827901`
- [x] T104 [US1] Session `form_login` firewall for `/admin` plus the Identity sign-in page: `backend/config/packages/security.yaml`, `backend/config/routes.yaml`, `backend/src/Identity/Infrastructure/Admin/AdminLoginController.php`, `backend/src/Identity/Infrastructure/Security/SecurityUser.php`, `backend/templates/admin/login.html.twig` (FR-030) — commit `32e18b7`
- [x] T105 [US1] Document the backoffice sign-in surface and its provisioning env vars in `.env.dist`, `README.md` and `docs/architecture.md` (FR-030, Constitution VI) — commit `6990e3c`
- [x] T106 [US1] Design artifacts for the increment: decision R11 in `specs/001-solo-ttrpg-assistant/research.md`, the no-migration form-binding contract in `specs/001-solo-ttrpg-assistant/data-model.md`, the validation walkthrough in `specs/001-solo-ttrpg-assistant/quickstart.md`, and the new `specs/001-solo-ttrpg-assistant/contracts/admin-backoffice.md` (FR-002, FR-003, FR-004) — commit `ef205e2`
- [x] T107 [US1] Keep the jsonb-backed `flowDefinition`/`sheetStructure` fields off the list and detail pages so `/admin/system` stops throwing once data exists, in `backend/src/Rulesets/Infrastructure/Admin/SystemCrudController.php` (FR-001) — commit `08a16c5`
- [x] T108 [US1] Structured `FlowDefinition` form types with lenient stage-name selects — `backend/src/Rulesets/Infrastructure/Admin/Form/FlowDefinitionType.php`, `FlowStageType.php`, `FlowTransitionType.php`, `LenientStageNameLoader.php`, `StageNameChoiceType.php` — plus kernel-less unit specs in `backend/tests/Unit/Rulesets/Infrastructure/Form/FlowDefinitionTypeTest.php` (FR-002, FR-003, FR-004) — commit `95ae477`
- [x] T109 [US1] Dedicated **Campaign flows** admin section over the same system entity: `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php`, `UpdatesFlowDefinition.php`, `JsonDocumentType.php`, `DashboardController.php`, the collection glue in `backend/public/assets/admin-flow-editor.js`, covered by `backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (FR-002, FR-003, FR-004, FR-005) — commit `5459d2b`
- [x] T110 [US1] Document the campaign-flows section and the systems/flows split in `README.md` and `docs/architecture.md` (FR-031, Constitution VI) — commit `1511584`

### Regressions the untracked increment introduced (US1)

- [x] T111 [US1] **A1 (critical)** — `GET /admin` 301'd to `http://localhost/admin/`, dropping the published port, because `public/admin/` shadowed the route. Failing test first: "the documented admin URL reaches the dashboard on its published port" in `frontend/tests/e2e/admin.spec.ts`; fixed by `absolute_redirect off` in `docker/nginx/default.conf` and by serving the editor from `backend/public/assets/admin-flow-editor.js` via `backend/src/Rulesets/Infrastructure/Admin/DashboardController.php` (FR-030, FR-031; `docs/audit/spec-compliance.md` A1; `docs/prompts/02-fix-admin-url.md`) — commits `ca9ea97`, `e2dff09`, `73e488a`, `228cbf9`
- [x] T112 [US1] **A2 (critical)** — the starting-stage/from/to selects rendered a single empty option because init called `syncSelects(document.body)` and `document.body.closest('form')` is always null. Failing test first: "the flow editor offers every stage and pre-selects the stored ones on load" in `frontend/tests/e2e/admin.spec.ts`; fixed in `backend/public/assets/admin-flow-editor.js` with the stored-value hint rendered by `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php` and `backend/templates/admin/flow_form_theme.html.twig` (FR-003, FR-004; `docs/audit/spec-compliance.md` A2; `docs/prompts/03-fix-flow-editor.md`) — commits `668baa7`, `8cb58b9`, `044b9ee`, `9512540`
- [x] T113 [US1] **A3 (high)** — once populated the selects offered the game system's own name as a stage, because `stageNames()` matched every `input[name$="[name]"]`. Failing test first: "the flow editor never offers the game system itself as a stage" in `frontend/tests/e2e/admin.spec.ts`; fixed by scoping the selector to `input[name*="[stages]"][name$="[name]"]` in `backend/public/assets/admin-flow-editor.js` (FR-003, FR-004; `docs/audit/spec-compliance.md` A3; `docs/prompts/03-fix-flow-editor.md`) — commit `281d5bd`

### Acceptance coverage the increment never shipped (US1)

- [ ] T114 [US1] Behat acceptance feature for authoring a campaign flow through the backoffice, in ubiquitous language (Constitution IV): save a reshaped flow, refuse removing a stage a campaign occupies naming that stage, refuse a transition pointing at a stage that does not exist — `backend/features/rulesets/author_campaign_flow.feature` plus the steps it needs in `backend/tests/Acceptance/Context/RulesetsContext.php` (FR-003, FR-004, FR-005; quickstart "Increment Validation" steps 4–6, currently manual)
- [ ] T115 [US1] Extend the admin browser suite in `frontend/tests/e2e/admin.spec.ts` to cover the rest of the quickstart increment walkthrough as automation rather than a checklist: the systems index rendering rows that carry jsonb payloads (step 2), a full flow save round-trip ending on the success flash (step 4), and the systems detail and oracles pages still rendering (step 7) (FR-001, FR-003, FR-004; quickstart "Increment Validation" steps 2, 4, 7)

---

## Phase 11: Convergence — Contract Payload Conformance

**Purpose**: Bring the payload gate and the drifts it found into the ledger they were built outside of

The audit's §2.2.6 recommendation was worked from `docs/prompts/15-contract-gate-payloads.md`
rather than from a task, so it is converged here. `scripts/check-contract.sh` compared only what
the API *declared*: gate A diffed paths and methods, gate B compared schema property sets, and
neither ever fetched a response body — which is why audit A5 shipped a `roll` IRI string where the
contract requires an embedded object while the gate printed *Contract OK*. Gate C closes that, and
on its first run it found three further drifts (`docs/audit/02-specs.md` §2.2.6); T117–T119 are
those, each fixed rather than exempted.

### Delivered work converged from commits (US2, US5)

- [x] T116 Gate C of the contract gate: register a fixture player, play a campaign through the whole loop against the live stack and validate every response body against the contract's schema for that operation — status code, media type, required properties, JSON types, enums, and a `$ref` to an object returned as an IRI string — plus gate B anchored on schema name and the three RFC 7807 schemas moved out of the skip list into live 422 assertions, in `scripts/check-contract.sh`, wired as merge gate 5 in `.github/workflows/ci.yml` and `AGENTS.md` (FR-016, FR-023, FR-027, FR-029, Constitution V; `docs/audit/02-specs.md` §2.2.6; `docs/prompts/15-contract-gate-payloads.md`) — commits `4961753`, `ba4d376`, `b5e9a02`
- [x] T117 [US2] `SuggestedAction` required a `label` the schema never defined, so no response could satisfy it — gate B could not see it because property-set coverage ignores `required`. Corrected to require `prompt`, the property `StageActionResource::$prompt` always emits, in `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml` (FR-014, FR-016; `docs/audit/02-specs.md` §2.2.6) — commit `7619e0d`
- [x] T118 [US2] `POST /campaigns/{campaignId}/advance` answered `201` where the contract declared `200`; gate A compares paths and methods, never status codes. Contract moved to `201` to match the shipped behaviour, in `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml` (FR-016; `docs/audit/02-specs.md` §2.2.6) — commit `3962d51`
- [x] T119 [US5] A character whose sheet requires nothing of it answered `"attributes": []`, a JSON array where the contract types an object. Failing test first: "An NPC with no attributes still answers an attributes object" in `backend/features/characters/sheets.feature` with the raw-body step in `backend/tests/Acceptance/Context/CharactersContext.php`; fixed by wrapping the map in an `\ArrayObject` at the single construction point in `backend/src/Characters/Infrastructure/Api/Provider/CharactersProvider.php` and opting `backend/src/Characters/Infrastructure/Api/CharacterResource.php` into `PRESERVE_EMPTY_OBJECTS`, regenerating `frontend/src/lib/api/schema.gen.ts` (FR-021, FR-022; `docs/audit/02-specs.md` §2.2.6) — commits `12532e5`, `2d0dc0d`

---

## Phase 12: Convergence — Lower-Severity Cleanup Sweep

**Purpose**: Bring the audit's eleven lower-severity fixes into the ledger they were worked outside of

The "Lower severity" table of `docs/audit/spec-compliance.md` §6 was worked from
`docs/prompts/16-cleanup-sweep.md` rather than from tasks, so it is converged here. Ten of the
eleven items landed; C1 and C9 belong to prompts 08 and 11 and were left alone by design. Two
items turned out to be larger than their one-line description and are recorded as such: C7's dead
handler was wired in rather than deleted, and C2's untyped login was closed by documenting the
firewall endpoint instead of moving it.

### Delivered work converged from commits (US1, US2, US3, US5, Polish)

- [x] T120 [US4/US6] C4 Closed dice and oracle panels rendered test scaffolding — "Dice roller closed." and "Oracles drawer closed." — to real users on the game master console; both return `null` now, in `frontend/src/components/dice/DiceRollerWidget.tsx` and `frontend/src/components/oracles/OracleDrawer.tsx`, with the two Vitest cases in `frontend/tests/components/dice/DiceRollerWidget.test.tsx` and `frontend/tests/components/oracles/OracleDrawer.test.tsx` flipped to assert the placeholder's absence (FR-026, FR-028; `docs/audit/spec-compliance.md` §6) — commit `161da02`
- [x] T121 [US1/US3] C3 EasyAdmin titled its pages after the bound persistence class ("Create PersistenceOracle", "Edit PersistenceGameSystem"); singular/plural entity labels added in `backend/src/Rulesets/Infrastructure/Admin/SystemCrudController.php`, `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php` and `backend/src/Oracles/Infrastructure/Admin/OracleCrudController.php`, with heading assertions in `backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` and `backend/tests/Integration/Oracles/AdminOracleSaveTest.php` (FR-001, FR-007, FR-030; `docs/audit/spec-compliance.md` §6) — commit `c3d64b7`
- [x] T122 C8 `.env.dist` shipped `ADMIN_EMAIL`/`ADMIN_PASSWORD` commented out, so the README's `app:create-admin` step failed for every new contributor; uncommented with placeholders in `.env.dist` and reconciled with `README.md` and `docs/functional-guide.md` §3, whose note and troubleshooting row about the failure are gone (FR-030; `docs/audit/spec-compliance.md` §6) — commit `1f86d02`
- [x] T123 C10 `docs/architecture.md` charted seven bounded contexts and omitted `Identity`, which owns users, roles, JWT issuance and the admin login; added in the same one-line-charter style (Constitution II, VI; `docs/audit/spec-compliance.md` §6) — commit `d264ebf`
- [x] T124 [US5] C12 `PATCH /api/characters/{characterId}` is the only campaign-scoped write with no operation-level `CAMPAIGN_OWNER` expression, and its ownership path had no test. Covered in `backend/tests/Integration/Characters/ForeignCharacterUpdateTest.php`, which asserts the stored character is unchanged and not merely that the status is 404 — removing the `OwnedCampaignFetcher` call from `backend/src/Characters/Application/UpdateCharacterHandler.php` leaves the 404 intact while the foreign write lands (FR-019, FR-023; `docs/audit/spec-compliance.md` §6) — commit `7722998`
- [x] T125 [US1] C7 `SetSystemStatusHandler` and `SetSystemStatusCommand` had no callers anywhere: availability was a raw `ChoiceField` write straight to the column. Wired through the Application layer per Constitution I rather than deleted, in `backend/src/Rulesets/Infrastructure/Admin/SystemCrudController.php`, covered by a form-driven toggle test in `backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (FR-001, FR-006; `docs/audit/spec-compliance.md` §6) — commit `a63e1a9`
- [x] T126 C6 `users.roles` was the one document column still on Doctrine's plain `json` while every other goes through `JsonbType`; mapping corrected in `backend/src/Identity/Infrastructure/Persistence/PersistenceUser.php` with the converting migration `backend/migrations/Version20260902120000.php` (Constitution VI; `docs/audit/spec-compliance.md` §6) — commit `e555e43`
- [x] T127 C5 `backend/migrations/Version20260822232549.php` is an empty auto-generated stub but is recorded as executed, so it was documented as an intentional no-op rather than deleted; the asymmetric `down()` methods in `backend/migrations/Version20260823075023.php`, `backend/migrations/Version20260823210000.php` and `backend/migrations/Version20260824010000.php` now mirror their `up()` in reverse (Constitution VI; `docs/audit/spec-compliance.md` §6) — commit `4f28c7b`
- [x] T128 C11 `backend/config/packages/api_platform.yaml` lists `jsonld` before `json`, so a client sending no `Accept` header gets Hydra envelopes rather than the documented shapes. Documented rather than reordered — reordering would change the default response type for every existing consumer — in `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml` and a comment in `backend/config/packages/api_platform.yaml` (Constitution V; `docs/audit/spec-compliance.md` §6) — commit `32f3bf4`
- [x] T129 C2 `POST /api/auth/login` is served by the `json_login` firewall listener, so API Platform had no metadata for it and the Lexik bundle documented it under the route *name* `api_auth_login`; it never reached `frontend/src/lib/api/schema.gen.ts` and `AuthGate.tsx` called it through an untyped `apiPath()` cast. `backend/src/Identity/Infrastructure/Api/OpenApi/LoginPathFactory.php` documents it at its real path with the contract's `AuthToken` component, wired in `backend/config/services.yaml`, pinned by `backend/tests/Integration/Identity/LoginContractTest.php`, and the now-obsolete `/auth/login` exception is removed from `scripts/check-contract.sh` (FR-030, Constitution V; `docs/audit/spec-compliance.md` §6) — commit `ccf651a`

### Deliberately not in this sweep

- C1 (`Bearer undefined`) belongs to `docs/prompts/08-session-lifecycle.md`
- C9 (the unused `openrtk` dependency) belongs to `docs/prompts/11-toolchain-hygiene.md`

---

## Phase 13: Convergence — Visual and Accessibility Regression

**Purpose**: Bring the design system's safety net into the ledger it was built outside of

Phase 4 of `docs/audit/03-design.md` §3.3 was worked from
`docs/prompts/20-visual-regression.md` rather than from a task, so it is converged here. The
app's accessibility — an `aria-label` on every region, `role="alert"` on the error surfaces,
`role="status"` on confirmations, a real label on every input — survived the token migration and
the primitives work because those prompts said so and because the E2E suite selects by role and
accessible name. That is a convention, not a gate: there was no accessibility assertion anywhere,
no visual baseline at all, and dark mode — the app's primary use case — had no automated cover of
any kind.

The three prompts before this one (`17-design-canvas`, `18-design-tokens`, `19-ui-primitives`,
commits `faf6529` through `dd3c9f1`) are **still unconverged**; they belong in a phase of their
own, or in the `specs/002-design-system/` folder the audit asks for, and are deliberately not
folded in here.

### Delivered work converged from commits (Polish)

- [x] T130 Visual, accessibility and dialog-behaviour suites over one shared walkthrough: twelve
  `toHaveScreenshot` baselines in `frontend/tests/e2e/visual.spec.ts`, an `@axe-core/playwright`
  scan failing on serious/critical in `frontend/tests/e2e/a11y.spec.ts`, and Escape / focus-trap /
  focus-return assertions for both drawers in `frontend/tests/e2e/drawer-dialogs.spec.ts` — six
  screens in both colour schemes throughout, driven through Playwright's `colorScheme` option —
  with the shared driving, axe floor and determinism helpers in
  `frontend/tests/e2e/support/journey.ts`, `axe.ts` and `deterministic.ts`, a second `visual`
  project in `frontend/playwright.config.ts` and `@axe-core/playwright` in
  `frontend/package.json` (FR-014, FR-018, FR-019, FR-026, FR-028; `docs/audit/03-design.md`
  §3.3 Phase 4) — commit `6c4f699`
- [x] T131 Pin the rendering environment so the baselines mean the same thing everywhere:
  `frontend/scripts/visual-e2e.sh` runs the `visual` project inside
  `mcr.microsoft.com/playwright:v<version>-noble`, the tag derived from `@playwright/test` so a
  dependency bump cannot leave the image behind, and the twelve images in
  `frontend/tests/e2e/__screenshots__/visual/linux/` are rendered there and reviewed one by one
  before freezing (`docs/audit/03-design.md` §3.3 Phase 4) — commits `6c4f699`, `aeaa1c2`
- [x] T132 Wire both gates into CI and document the workflow: the pinned-image pull and the
  `visual-diffs` artifact in `.github/workflows/ci.yml`, the full workflow in
  `docs/testing-visual-regression.md`, and the reviewer-facing rules beside the eight merge gates
  in `AGENTS.md` and `README.md` — that `--update-snapshots` is a deliberate act, that an
  unexplained baseline update is a review flag, and that lowering the axe floor to pass a screen
  is the same class of act as deleting a test (Constitution VI) — commit `b4434f9`

### Findings the new gates surfaced

- [ ] T133 The dice notation field keeps an accessible name through its `placeholder` when its
  `<label>` is removed, so `axe-core` stays green while the name silently degrades from "Dice
  notation" to "e.g. 1d20+5" — the label-removal regression check in
  `frontend/src/components/dice/DiceRollerWidget.tsx` passed both gates. Cover the name itself
  with a role-and-name assertion (`getByRole('textbox', { name: 'Dice notation' })`) in
  `frontend/tests/e2e/drawer-dialogs.spec.ts`, and do the same for every other control whose
  label a placeholder would mask (FR-026; `docs/testing-visual-regression.md`)

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
