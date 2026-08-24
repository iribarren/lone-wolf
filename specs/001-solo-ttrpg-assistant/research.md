# Phase 0 Research: Lone Wolf — Solo TTRPG Digital Assistant

**Feature**: `001-solo-ttrpg-assistant` | **Date**: 2026-08-22

Resolves every technical unknown raised by plan.md's Technical Context and the user's phase outline.
The spec itself contains zero `[NEEDS CLARIFICATION]` markers (validated by its quality checklist);
all items below are technology/design resolutions required by the Constitution's fixed stack.

---

## R1. Symfony version vs. "Symfony LTS" constitution constraint

**Decision**: Symfony **7.4 LTS**.

**Rationale**: The Constitution fixes "PHP 8.3+ on Symfony LTS" while the phase outline mandates
"Setup Symfony 7 in /backend". Symfony 7.4 is the current LTS release of the 7 major line, so both
constraints are satisfied simultaneously with no amendment required.

**Alternatives considered**:
- *Symfony 6.4 LTS*: older LTS, but PHP-8.3-native features and current ecosystem support favor 7.x; rejected as already legacy for a greenfield 2026 project.
- *Symfony 7.x standard (non-LTS) minor*: would violate the Constitution's LTS wording without justification; rejected.
- *Amending the Constitution*: unnecessary given 7.4 resolves the conflict.

## R2. Frontend API-client generation strategy

**Decision**: `openapi-typescript` to generate types from the backend's OpenAPI document + a thin
typed fetch wrapper, consumed through TanStack Query hooks.

**Rationale**: Keeps Principle V literally true — the contract file is the single source of truth;
regeneration is deterministic and cheap; no runtime client library lock-in beyond fetch. TanStack Query
adds caching/loading/error states that a guided-play UI (stage prompts, journal pagination) needs anyway.

**Alternatives considered**:
- *orval / openapi-generator (TS)*: heavier codegen producing full service classes; more generated surface to review, more drift risk; rejected for lean monorepo.
- *Hand-written client*: violates contract-first spirit, invites drift between stacks; prohibited by review checklist.
- *GraphQL*: not RESTful-per-Constitution stack pillar; rejected outright.

## R3. Authentication & role model across decoupled stacks

**Decision**: JWT bearer auth via `lexik/jwt-authentication-bundle` integrated into API Platform;
two roles — `ROLE_ADMIN` (backoffice) and `ROLE_PLAYER`. Player registration/login endpoints are part
of the OpenAPI contract; EasyAdmin backoffice uses the same firewall.

**Rationale**: Session cookies across a Next.js↔Symfony split violate the "no session sharing" clause of
Principle V. JWT keeps the frontend stateless and the contract explicit. Spec assumptions only demand
standard accounts/ownership privacy (FR-019, FR-030), which roles + per-user query scoping satisfy.

**Alternatives considered**:
- *Session-cookie auth*: simplest, but couples stacks through shared cookie/session semantics; conflicts with Principle V; rejected.
- *OAuth2/OIDC server*: over-engineering for v1 solo-player scale ("speculative abstraction is rejected"); deferred.

## R4. Flexible character sheets: JSONB modeling + validation

**Decision**: Each `GameSystem` owns a `SheetStructure` value object — an ordered list of field
definitions `{key, label, type: text|number|boolean|select, options?, requiredForPc, requiredForNpc}`.
`Character.attributes` persists as PostgreSQL **JSONB**, validated in the Domain against the owning
system's structure at every write (PC vs NPC requirement sets). Characters record the structure
version they were validated against; if the admin later changes the structure and stored data no longer
conforms, the character is **flagged for review** (readable + editable, never hidden/altered).

**Rationale**: FR-022/023/024/025 require system-shaped, strictly validated, drift-detectable sheets.
A schema-on-write approach puts all rules in pure Domain code (testable per Constitution IV) while JSONB
keeps storage schemaless. Version stamping makes drift detection deterministic rather than heuristic.

**Alternatives considered**:
- *EAV tables (attribute rows)*: query-heavy joins for marginal flexibility; painful ordering/typing; rejected.
- *Per-system Doctrine entity inheritance/dynamic columns*: DDL at runtime is fragile and untestable in pure units; rejected.
- *Storing full JSON Schema documents per system*: more expressive than needed; custom field-definition VO keeps domain language ubiquitous and validation messages field-level per FR-023.

## R5. Oracle polymorphic scoping (System-Specific vs System-Agnostic)

**Decision**: `OracleScope` value object with an explicit discriminator persisted as two nullable-ish
columns (`scope_type ENUM('system','global')`, `scope_system_id UUID NULL`) with a partial unique index
enforcing integrity. Visibility query for a campaign = `scope_type='global' OR scope_system_id = :campaignSystemId`.

**Rationale**: FR-008/009 need exactly one-of-two scoping semantics with clean domain expression. A VO +
discriminator avoids Doctrine inheritance mapping overhead while making the domain rule explicit and
unit-testable; DB constraint guards against orphaned system scopes.

**Alternatives considered**:
- *Nullable system_id alone* (`NULL` = global): works, but the domain meaning is implicit and easy to misuse in code; weaker ubiquitous language; rejected.
- *Doctrine single-table inheritance (GlobalOracle/SystemOracle)*: two aggregates for one concept complicates repositories/EasyAdmin for zero behavioral gain; rejected.
- *Join-table many-systems-per-oracle*: spec demands exactly-one-or-global; over-generalization; rejected.

## R6. Flow Engine pattern: State vs Strategy

**Decision**: **State pattern** for campaign pacing, parameterized by data. `CampaignFlowDefinition`
(stages + transition graph + starting stage) is immutable state; the engine exposes
`legalNextStages(position)`, `assertCanAdvance(from,to)`, and guidance/prompt derivation
("Open Scene", "Run Sequel", "Close Act") from outgoing edges. Stage advancement mutates the
Campaign's `StagePosition` only after engine validation. Strategy pattern appears where variance is
behavioral: oracle scope resolution and random-source injection.

**Rationale**: Acts/Scenes/Beats differ per game only by names/graph shape (admin-authored data),
not by code behavior — so hard-coding states as classes would be speculative abstraction. A generic
graph-driven state machine covers strict scene/sequel loops, act/beat ladders, and freeform sandboxes
alike (SC-007). The user outline allows "State or Strategy"; this splits them along their actual axis
of variation.

**Alternatives considered**:
- *One State class per stage kind (SceneState, ActState…)*: forces new deploys per game system; contradicts platform-dictates-rules promise; rejected.
- *Third-party workflow bundles (e.g., symfony/workflow)*: pulls framework semantics into Domain, violating Principle I; the graph logic is ~100 lines of pure PHP; rejected.
- *Event-sourced flow log*: valuable history comes free from journal entries; event sourcing is unjustified complexity for v1.

## R7. Cross-context integration (Campaigns ↔ Rulesets)

**Decision**: Shared kernel holds only identifiers (`GameSystemId`, `StageId` as typed VOs). Campaigns
context receives flow definitions through an Application-owned port
(`interface FlowDefinitionProvider`) implemented in Rulesets' Infrastructure. Journal entries store a
denormalized copy of stage name/id for stable historical display even after later renames.

**Rationale**: Keeps contexts independently deployable/testable (Constitution II) while avoiding
duplicate definitions (FR-031). Denormalizing stage labels protects journal readability against
legitimate future renames without blocking edits.

**Alternatives considered**:
- *Single mega-context*: collapses bounded contexts, prohibited by Constitution II.
- *Domain events + eventual consistency*: adds async infrastructure before any need exists; rejected as speculative.
- *Direct entity cross-references (Doctrine relations across contexts)*: creates coupling the contexts are meant to prevent; rejected.

## R8. Determinism & testing of randomness (oracles, dice)

**Decision**: Ports `RandomSource` (int-in-range) and `Clock` defined in shared kernel; production
adapters wrap PHP RNG/time; tests inject seeded fakes. Oracle weighting uses cumulative-weight binary
search over injected uniform draws; PHPUnit asserts distribution within tolerance on large seeded runs
(SC-004 ±5 % @ 10k).

**Rationale**: Constitution IV explicitly forbids network-dependent or non-injected collaborators;
statistical acceptance criteria must be reproducible in CI.

**Alternatives considered**:
- *Static `random_int` calls in domain*: untestable determinism; violates Principle IV; rejected.
- *Property-based testing libs*: nice-to-have later; plain seeded statistical suites satisfy SC gates now.

## R9. Docker composition for the three runtimes

**Decision**: `docker-compose.yml` with `php` (php:8.3-fpm + Composer + pdo_pgsql/intl/opcache),
`nginx` proxying FastCGI to it, `postgres` (volume-backed), and `frontend` (node:22 running
`next dev`). Root `.env.dist` documents variables; secrets stay local.

**Rationale**: Matches the phase outline exactly (PHP 8.3, PostgreSQL, Node.js); FPM+Nginx is the
boring, well-documented Symfony-in-Docker path; one command boots the whole contract-first loop.

**Alternatives considered**:
- *FrankenPHP/Caddy single container*: fewer moving parts, but less conventional and harder to debug for contributors; rejected for v1.
- *No Docker (local PHP/Node)*: contradicts explicit phase-1 requirement; rejected.
- *Kubernetes/Helm*: massive over-provisioning for this scale; rejected.

## R10. Behat scope and frontend E2E layering

**Decision**: Backend Behat drives HTTP-level scenarios in ubiquitous language (campaign start →
guidance → advance → refuse-illegal → resume; oracle consult distribution smoke; dice notation matrix).
Frontend relies on Vitest component tests + a thin Playwright journey mirroring quickstart.md.
Both suites run offline (in-memory doubles for RNG/clock; Playwright against compose stack).

**Rationale**: Splits responsibilities cleanly: Behat proves the automated-GM contract; Playwright
proves the UI reacts to engine output (SC-003's prompt anticipation). Avoid duplicating full journeys
in both layers.

**Alternatives considered**:
- *Behat driving browser via Gherkin+Mink*: slow, brittle selector coupling; rejected.
- *Playwright-only E2E*: loses executable specifications in domain language demanded by Constitution IV; rejected.

---

## R11. Increment — admin backoffice: system index crash + dedicated Campaign-flows editor

**Decision**: Fix the `/admin/system` index crash by keeping the jsonb-backed
`flowDefinition`/`sheetStructure` fields off list/detail pages (EasyAdmin's
`TextConfigurator` throws on non-stringable values before `formatValue` runs).
Replace raw-JSON flow editing with a second CRUD controller over the same
`PersistenceGameSystem` entity (`GameFlowCrudController`, index + edit only)
rendering structured Symfony forms (`FlowDefinitionType` → stages collection,
starting-stage select, transitions collection). Stage-name selects use a lenient
`ChoiceLoader` (accepts any submitted name) so the domain stays the single
validation authority; a small static JS asset populates prototype-row selects
and wires collection add/remove.

**Rationale**: EasyAdmin supports multiple CRUD controllers per entity and
derives route names from the controller short name (`game_flow`), which the
existing menu already relies on for systems/oracles. Lenient loaders avoid the
classic fragile PRE_SUBMIT choice-rebuild dance while `UpdateFlowDefinitionHandler`
keeps enforcing FR-005 occupancy guards, domain invariants, and supersede
warnings unchanged. No schema or API contract changes are required.

**Alternatives considered**:
- *Structured forms inside the existing Systems CRUD only*: leaves flows
  undiscoverable (the reported menu gap); rejected.
- *Separate flow entity/table*: violates FR-002 1:1 ownership and needs a
  migration; rejected.
- *Dynamic choice rebuilding via form events*: brittle with collections;
  rejected in favour of loader + JS.
- *New Behat web-driving context*: out of scope for this increment; kernel-client
  integration tests cover the new pages (matches AdminBackofficeLoginTest style).

**Outcome**: All technical unknowns resolved. No remaining NEEDS CLARIFICATION items. Gates re-checked
post-design in plan.md remain PASS.
