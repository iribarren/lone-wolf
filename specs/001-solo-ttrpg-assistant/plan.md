# Implementation Plan: Lone Wolf — Solo TTRPG Digital Assistant

**Branch**: `001-solo-ttrpg-assistant` | **Date**: 2026-08-22 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-solo-ttrpg-assistant/spec.md`

## Summary

Lone Wolf is a multi-system solo TTRPG digital assistant. Administrators author game systems (Rulesets) and their Campaign Flows (ordered stages + legal transitions + starting stage), plus scoped oracles; players run campaigns guided by a Flow Engine that paces play (Acts/Scenes/Beats are just named stages of a generic flow model), journal per stage, consult weighted oracles, maintain system-shaped characters (JSONB-backed flexible sheets), and roll dice with standard notation.

Technical approach: monorepo (`/backend` Symfony 7 LTS, `/frontend` React + Next.js) communicating exclusively through an OpenAPI-documented REST contract (API Platform). Backend is Hexagonal (Domain / Application / Infrastructure, dependencies inward-only) organized by Bounded Contexts (Rulesets, Campaigns, Journal, Oracles, Characters, Dice). PostgreSQL persists via Doctrine adapters (JSONB for character attributes and flow definitions); EasyAdmin powers the admin backoffice; PHPUnit pure tests cover Domain/Application without booting the framework; Behat verifies end-to-end behavior in ubiquitous language.

## Technical Context

**Language/Version**: PHP 8.3+ (strict_types everywhere) / TypeScript 5.x on Node.js 22 LTS

**Primary Dependencies**: Symfony 7.4 LTS, API Platform (REST + OpenAPI/Swagger), Doctrine ORM (DBAL JSONB), EasyAdminBundle 4, PHPStan (max level), PHPUnit 11, Behat; Next.js (React), openapi-typescript-generated client, TanStack Query, Vitest + Playwright

**Storage**: PostgreSQL (JSONB for flexible character attributes & system sheet structures)

**Testing**: PHPUnit (pure domain/application — no framework boot), Behat (E2E in ubiquitous language), Vitest + Playwright (frontend)

**Target Platform**: Linux server via Docker Compose (PHP-FPM, PostgreSQL, Node.js); evergreen desktop browsers

**Project Type**: Web application — REST API backend + player web app + admin backoffice

**Performance Goals**: Campaign journal with 500 entries loads latest view < 2 s (SC-008); oracle distribution within ±5 % over 10 k consultations (SC-004)

**Constraints**: Online-only v1; solo play (one player per campaign); pacing engine advises only — every stage change is player-confirmed; dice scope v1 = `NdM±K`; English UI; platform ships without bundled content (admin-authored, optional seed fixtures)

**Scale/Scope**: Single-tenant community scale; ~7 bounded contexts; 2 frontends (player app, admin backoffice); 31 functional requirements across 6 user stories (P1–P5)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Principle | Status | Evidence / Justification |
|------|-----------|--------|--------------------------|
| Hexagonal Architecture | I | PASS (by design) | All code lives under `backend/src/<Context>/{Domain,Application,Infrastructure}`; Domain = pure PHP 8.3, zero framework imports; Application owns ports; Infrastructure implements them (Doctrine repos, API Platform controllers, EasyAdmin). Enforced in review + PHPStan custom rules planned (Phase 2). |
| DDD Bounded Contexts | II | PASS (by design) | Contexts: Rulesets, Campaigns, Journal, Oracles, Characters, Dice (+ shared kernel for identifiers). No global Entity/Repository/Controller folders. Cross-context collaboration only through context-owned ports or published identifiers. |
| Strict Typing & SOLID | III | PASS (by design) | `declare(strict_types=1)` mandated in every file; native type declarations on all members; PHPStan level max as quality gate; DI containers wired around small interfaces. |
| Testing Discipline | IV | PASS (by design) | Domain/Application covered by pure PHPUnit (no Symfony boot); Behat scenarios in ubiquitous language verify E2E; clock/random collaborators injected (`Clock`, `RandomSource` ports) making oracle/dice tests deterministic. |
| Contract-First Decoupled API | V | PASS (by design) | Single source of truth: OpenAPI contract in `contracts/` (authored first, verified against API Platform's generated spec at runtime). Frontend consumes generated client only — no DB access, no session sharing, no server-side templating. Breaking changes require versioned migration path. |
| Documentation Parity | VI | PASS (by design) | Each phase's tasks include doc updates (README, docs/ architecture notes, quickstart refresh) in the same change set; PR checklist cites docs gate. |

No violations to justify → Complexity Tracking remains empty.

**Post-Phase-1 re-check (after research.md, data-model.md, contracts/openapi.yaml)**: all six gates
re-evaluated against concrete design — Domain models (FlowEngine, OracleScope strategy,
SheetStructure validation) contain zero framework imports; contracts are authored before
implementation; injected `Clock`/`RandomSource` ports keep suites deterministic; EasyAdmin is the only
server-rendered surface (admin tooling inside the backend, not frontend↔backend coupling).
Status: **ALL GATES STILL PASS**. No NEEDS CLARIFICATION items remain.

## Project Structure

### Documentation (this feature)

```text
specs/001-solo-ttrpg-assistant/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   └── openapi.yaml     # Canonical REST contract (contract-first)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
lone-wolf/
├── docker-compose.yml           # php-fpm 8.3 + nginx, postgres, node/frontend
├── backend/
│   ├── Dockerfile               # PHP 8.3-fpm + composer
│   ├── composer.json
│   ├── phpstan.neon             # level max
│   ├── behat.yml
│   ├── src/
│   │   ├── Shared/                        # shared kernel: identifiers, Clock, RandomSource ports
│   │   │   └── Domain/
│   │   ├── Rulesets/                      # GameSystem + Campaign Flow authoring
│   │   │   ├── Domain/                    # GameSystem, FlowStage, Transition, FlowDefinition
│   │   │   ├── Application/               # use cases + ports (RulesetRepository)
│   │   │   └── Infrastructure/            # Doctrine mapping/repo, API Platform resources, EasyAdmin CRUD
│   │   ├── Campaigns/                     # campaign lifecycle + Flow Engine state machine
│   │   │   ├── Domain/                    # Campaign, FlowEngine (State pattern), StagePosition
│   │   │   ├── Application/               # StartCampaign, AdvanceStage handlers + ports
│   │   │   └── Infrastructure/
│   │   ├── Journal/                       # stage-scoped entries; references rolls/oracle results
│   │   │   ├── Domain/  ├── Application/  └── Infrastructure/
│   │   ├── Oracles/                       # weighted tables, polymorphic scoping (system vs global)
│   │   │   ├── Domain/                    # Oracle, OracleEntry, OracleScope (Strategy)
│   │   │   ├── Application/               # ConsultOracle handler (injected RandomSource)
│   │   │   └── Infrastructure/
│   │   ├── Characters/                    # PC/NPC, system-shaped sheets, drift flagging
│   │   │   ├── Domain/                    # Character, SheetStructure, AttributeValue validation
│   │   │   ├── Application/
│   │   │   └── Infrastructure/            # Doctrine JSONB mapping
│   │   └── Dice/                          # notation parser + roller (NdM±K)
│   │       ├── Domain/  ├── Application/  └── Infrastructure/
│   ├── tests/
│   │   ├── Unit/                          # pure domain/application (no kernel boot)
│   │   ├── Integration/                   # Doctrine adapters, JSONB round-trips
│   │   └── Acceptance/                    # Behat features in ubiquitous language
│   └── migrations/
└── frontend/
    ├── Dockerfile               # Node.js 22
    ├── package.json             # Next.js (App Router) + TypeScript strict
    ├── src/
    │   ├── app/                           # routes: systems, campaigns/[id], journal, play widgets
    │   ├── components/                    # stage guidance panel, floating dice roller, oracle drawer
    │   ├── lib/api/                       # GENERATED OpenAPI client (openapi-typescript) — never hand-edited
    │   └── lib/hooks/                     # TanStack Query wrappers over generated client
    └── tests/
```

**Structure Decision**: Option 2 (web application with `backend/` + `frontend/`) from the template, expanded into bounded-context packages inside `backend/src/` per Constitution II. The monorepo root carries Docker orchestration so one `docker compose up` boots the whole stack.

## Implementation Phases

Delivery is broken into five sequential phases; each ends at a constitution gate checkpoint (architecture → quality → test → contract → docs).

### Phase 1 — Foundation & Monorepo Setup

1. Initialize monorepo layout: create `/backend` and `/frontend` directories at repo root; add top-level README describing the two-stack split and how they communicate (Constitution V, VI).
2. Author `docker-compose.yml` with services:
   - `php`: PHP 8.3-fpm image with Composer, PHP extensions (`pdo_pgsql`, `intl`, `zip`, `opcache`);
   - `nginx`: serves the backend entrypoint, proxies to php-fpm;
   - `postgres`: PostgreSQL volume-backed service;
   - `frontend`: Node.js 22 image running the Next.js dev server.
   Provide per-service Dockerfiles and `.env.dist` templates (no secrets committed).
3. Add Makefile / composer scripts for common flows (`up`, `down`, `logs`, `test`, `lint`).
4. Verify: `docker compose up` yields reachable backend health endpoint stub and frontend placeholder page.

### Phase 2 — Backend Core & DDD Scaffolding

1. Install Symfony 7 (web-app skeleton) into `/backend`; configure it against the compose PostgreSQL service.
2. Create the hexagonal folder skeleton inside `src/`: one directory per bounded context (Rulesets, Campaigns, Journal, Oracles, Characters, Dice) each containing `Domain/`, `Application/`, `Infrastructure/`; add `Shared/Domain` for cross-context identifiers and generic ports (`Clock`, `RandomSource`). Global `Entity/`, `Repository/`, `Controller/` folders are prohibited.
3. Install and configure PHPUnit 11 (unit suite boots NO kernel — separate phpunit.xml suites) and PHPStan at max level with `strict_rules` (checkbaldwin/… or phpstan/phpstan-strict-rules) enforcing `declare(strict_types=1)` and full typing.
4. Add a lightweight architectural test (depfile/reflection-based) asserting Domain namespaces import nothing from Symfony/Doctrine/Application/Infrastructure.
5. Verify: empty-context builds pass `vendor/bin/phpunit` + `vendor/bin/phpstan analyse` green in CI-style local run.

### Phase 3 — Core Domain & Flow Engine (Backend)

1. Implement Rulesets Domain model: `GameSystem` (identity, name, description, availability status), owning exactly one immutable-per-campaign `FlowDefinition` composed of named `FlowStage`s, legal `Transition`s, and one designated starting stage. Pure PHP, native types only.
2. Build the Flow Engine in Campaigns Domain using the State pattern: `FlowEngine` validates candidate transitions against the definition (`canAdvance`, `legalNextStages(current)`, `startingStage()`), produces pacing guidance (next-action prompts such as "Open your Scene", "Run Sequel", "Close Act"). No database, no framework — Acts/Scenes/Beats are data-driven stage names.
3. Implement Application-layer handlers: `StartCampaignHandler` (positions campaign on system's starting stage), `AdvanceStageHandler` (refuses illegal moves, returns explanation of legal alternatives).
4. Implement Oracles Domain model with polymorphic system-linking: `OracleScope` strategy — `SystemScope(systemId)` vs `GlobalScope`; weighted `OracleEntry` selection via injected `RandomSource` port; empty-table consultation yields explicit "empty table" outcome object, not an exception leak.
5. Write comprehensive PHPUnit unit tests: flow state transitions (legal/illegal/start/dead-end), guidance generation, oracle weighting distribution (statistical tolerance on seeded RNG), scoping visibility matrix, all without booting Symfony.

### Phase 4 — Infrastructure, Persistence & Admin (Backend)

1. Implement Doctrine ORM adapters implementing the Application-owned repository ports for every context; entities mapped via XML/attributes in `Infrastructure/Persistence`.
2. Configure PostgreSQL JSONB columns (Doctrine `json` type on `jsonb` column via DBAL type mapping) for character attributes and system sheet structures; implement `SheetStructure` value-object validation (field names, types, required-for-PC vs required-for-NPC) and drift-flagging when stored attributes no longer match an updated structure (never silently altered).
3. Install EasyAdminBundle; build backoffice CRUD for game systems, their flows/stages/transitions (with FR-005 guard: occupied-stage removal/renaming blocked), and oracles (scoping picker + weighted entries editor).
4. Expose REST endpoints via API Platform implementing the contract: systems list (available-for-play), campaigns (create/read/advance with current stage + suggested next actions), journal entries, characters, dice rolls, oracle listing scoped per campaign, oracle consultation, dice rolling. Wire JWT authentication (admin vs player roles, FR-030/031).
5. Publish generated `/docs` OpenAPI spec endpoint; add integration tests (Doctrine round-trips incl. JSONB) and Behat acceptance scenarios covering US1–US6 journeys.

### Phase 5 — Frontend Scaffolding & GM UI Implementation

1. Scaffold Next.js (App Router, TypeScript strict) in `/frontend` inside its Docker service; wire env pointing to the backend base URL.
2. Generate the typed API client from the backend's OpenAPI document (`openapi-typescript` + fetch wrapper); hand-written code may only call the generated client — direct fetch URLs are prohibited (Constitution V).
3. Build the Campaign Interface as a structure-aware GM console (not a flat journal): current-stage card rendering the engine's guidance, "Start Scene"-style prompt buttons derived from legal transitions returned by the API, advance controls with refusal feedback showing legal alternatives, stage-grouped chronological journal view, resume-exactly-where-you-left-off on reopen.
4. Integrate floating widgets available during play: Dice Roller (notation input, individual dice + modified total display, log-to-journal action) and Oracle tables drawer (scoped table list, consult action, save-result-to-journal with interpretation field, friendly empty-table notice).
5. Add Vitest component tests for guidance/prompt rendering states and Playwright E2E smoke covering the quickstart scenario; update README/docs in the same change set.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| *(none)* | — | — |

*(empty — no gate violations identified pre-design)*
