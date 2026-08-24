# Phase 1 Data Model: Lone Wolf — Solo TTRPG Digital Assistant

**Feature**: `001-solo-ttrpg-assistant` | **Date**: 2026-08-22

Domain model organized by Bounded Context (Constitution II). Entities below are pure-PHP Domain
concepts; persistence notes describe their Infrastructure adapters only.

---

## Bounded Context Map

```text
Shared kernel: identifiers (GameSystemId, StageId…), Clock, RandomSource ports
┌─────────────┐   owns    ┌──────────────────┐
│  Rulesets   │──────────▶│ FlowDefinition    │  admin-authored structure
└──────┬──────┘           └──────────────────┘
       │ referenced by id            ┌────────────────────────────┐
       ▼                             │         Campaigns          │
┌─────────────┐    scoped-by id      │ Campaign + FlowEngine(SM)  │
│   Oracles   │◀────────────────────▶└──────┬──────────┬──────────┘
└─────────────┘        consulted-in     │ owns      │ owns
                                ┌───────▼───┐  ┌────▼────────┐
                                │  Journal  │  │ Characters  │
                                └───────────┘  └─────────────┘
                     ┌──────┐
                     │ Dice │  (stateless mechanics, logged into Journal)
                     └──────┘
```

---

## Rulesets Context

### GameSystem (aggregate root)

| Field | Type | Rules |
|-------|------|-------|
| id | GameSystemId (UUID VO) | immutable |
| name | non-empty string ≤120 | unique among systems |
| description | string ≤2000 | may be empty |
| status | enum `active` \| `inactive` | default `active`; FR-001/FR-006 |
| sheetStructure | SheetStructure VO | ≥1 field definition; keys unique |
| flowDefinition | FlowDefinition VO (owned 1–1) | FR-002 |

**Behavior**: `activate()`, `deactivate()` (existing campaigns unaffected — FR-006).

### SheetStructure (value object)

Ordered list of `FieldDefinition`:

| FieldDefinition | Type | Rules |
|---|---|---|
| key | snake_case string | unique within structure |
| label | string | display name |
| type | enum `text`\|`number`\|`boolean`\|`select` | select ⇒ `options` non-empty |
| options | string[] \| null | only for `select` |
| requiredForPc | bool | FR-022 |
| requiredForNpc | bool | FR-024 (may be false where PC true ⇒ lighter NPC set) |

Carries an incrementing `version` stamp used for drift detection (FR-025).

### FlowDefinition (value object owned by GameSystem)

| Member | Type | Rules |
|--------|------|-------|
| stages | FlowStage[] (≥2) | `{id: StageId, name: string}` names unique |
| transitions | FlowTransition[] | `{from: StageId, to: StageId}`, no self-loops, both endpoints exist |
| startingStageId | StageId | MUST reference an existing stage (FR-004); every stage reachable? not required (sandbox flows may have side pockets) |

**Invariants enforced in constructor** (fail fast): FR-004 exactly-one-start; transition endpoints valid;
≥2 stages.

**Modification guards (Application layer)**: removing/renaming a stage that any live Campaign occupies is
refused (FR-005) via `StageOccupancyChecker` port answered by Campaigns context.

---

## Campaigns Context

### Campaign (aggregate root)

| Field | Type | Rules |
|-------|------|-------|
| id | CampaignId (UUID VO) | immutable |
| playerId | UserId | owner; all queries scoped to owner (FR-019) |
| gameSystemId | GameSystemId | bound exactly once at creation (FR-012); never re-bound |
| currentPosition | StagePosition VO | `{gameSystemId, stageId}` — engine-validated |
| createdAt / updatedAt | DateTimeImmutable via Clock port | |

**Creation rule**: `StartCampaignHandler` reads the system's `startingStageId` and positions the
campaign there (FR-013); guidance DTO returned alongside.

**Deletion**: hard delete of aggregate + cascaded JournalEntries & Characters; API requires explicit
`confirm: true` (FR-020).

### FlowEngine (domain service — State pattern, graph-driven)

Pure functions over `FlowDefinition` + current position:

- `legalNextStages(definition, position): FlowTransition[]`
- `assertCanAdvance(definition, position, target)` — throws `IllegalStageTransitionException`
  carrying the list of legal alternatives (FR-016)
- `guidance(definition, position): Guidance` — prompt strings derived from outgoing edges;
  terminal position yields conclusion guidance instead of advance actions (spec US2-5)

Acts/Scenes/Beats are **data** (stage names authored by admins), not code states.

**Campaign stage-position state machine**:

```text
[created] --StartCampaignHandler--> positioned at startingStage
[positioned] --AdvanceHandler(to)--> positioned at `to`   ⇢ only if edge exists, else refused
[positioned] --resume anytime--> same position (persistence, FR-018)
```

---

## Oracles Context

### Oracle (aggregate root)

| Field | Type | Rules |
|-------|------|-------|
| id | OracleId (UUID VO) | |
| title | non-empty string ≤160 | |
| scope | OracleScope VO | FR-008: `GlobalScope` \| `SystemScope(GameSystemId)` |
| entries | OracleEntry[] | consultable set; MAY be empty at rest (friendly notice path) |

### OracleEntry (entity within aggregate)

| Field | Type | Rules |
|-------|------|-------|
| id | OracleEntryId | |
| text | non-empty string ≤500 | result wording |
| weight | int > 0 | relative likelihood (FR-007/FR-010) |

### OracleScope (Strategy value object)

- `GlobalScope` — visible to every campaign.
- `SystemScope(GameSystemId)` — visible only to campaigns of that system.

Visibility predicate (FR-009): `scope.isGlobal() OR scope.systemId == campaign.gameSystemId`.

### ConsultationOutcome (result object, not exception)

`{selected: OracleEntry}` **or** `{emptyTable: true}` — FR-011 friendly path. Weighted selection uses
injected `RandomSource`; cumulative-weight pick verified statistically (SC-004 ±5 % @ 10k seeded runs).

Retired/deleted oracles referenced by past journal entries: journal stores a **snapshot**
(title + result text), so history stays readable; consultation of missing oracle returns
`{unavailable: true}` handled gracefully (Edge Cases §3).

---

## Journal Context

### JournalEntry (aggregate root per campaign)

| Field | Type | Rules |
|-------|------|-------|
| id | JournalEntryId | |
| campaignId | CampaignId | owner-scoped access (FR-019) |
| stageId / stageName | StageId + denormalized copy | captured at write time (FR-015); copy survives later renames |
| kind | enum `narrative` \| `oracle_result` \| `dice_roll` | |
| narrative | text ≤10 000 | required for `narrative` |
| oracleSnapshot | `{oracleTitle, resultText}` \| null | for `oracle_result` |
| rollSnapshot | `{notation, diceValues[], modifier, total}` \| null | for `dice_roll` (FR-029) |
| createdAt | Clock-backed timestamp | |

**Read model**: chronological index `(campaign_id, created_at DESC)` grouped client-side by stage
(FR-017); target SC-008 (<2 s @ 500 entries) satisfied by covering index + keyset pagination.

---

## Characters Context

### Character (aggregate root)

| Field | Type | Rules |
|-------|------|-------|
| id | CharacterId | |
| campaignId | CampaignId | inherits owner scoping |
| kind | enum `pc` \| `npc` | FR-021/FR-024 |
| name | non-empty string ≤120 | |
| attributes | AttributesMap VO (JSONB) | keys/values conform to owning system's SheetStructure |
| validatedStructureVersion | int | version stamp at last conforming write |
| reviewStatus | enum `clean` \| `flagged_for_review` + `driftIssues: string[]` | FR-025 |

**Write-time validation** (Domain): for `pc`, every `requiredForPc` key present & correctly typed &
select-values ∈ options; for `npc`, only `requiredForNpc` enforced. Violations produce
field-level messages keyed by attribute key (FR-023) — no partial save.

**Drift detection**: on read/update, if stored attributes fail against current structure version,
character surfaces as flagged (readable/editable, data untouched).

---

## Dice Context

### DiceRoll (value object; persisted only via journal snapshots)

| Field | Type | Rules |
|-------|------|-------|
| notation | canonical string | `NdM±K` (K optional) — e.g. `2d6`, `1d20+5`, `3d6-2` |
| diceValues | positive int[] | length = N |
| modifier | int | K (0 if absent) |
| total | int | Σ diceValues ± modifier (FR-028) |
| rolledAt | Clock timestamp | |

**Parser bounds** (refusals before any roll — FR-026/FR-027, Edge Case §5):
`N ∈ [1,50]`, `M ∈ [2,1000]`, `K ∈ [-10 000, 10 000]`. Specific failure messages distinguish
malformed syntax (`2d`, `d20x`), zero/negative counts (`0d6`), invalid faces (`1d0`), overflow.

---

## Shared Kernel

| Element | Purpose |
|---------|---------|
| Typed identifier VOs (`GameSystemId`, `CampaignId`, …) | prevent primitive obsession across contexts |
| `Clock` port | injectable time (Constitution IV) |
| `RandomSource` port | injectable randomness (Constitution IV) |

---

## Persistence Notes (Infrastructure adapters)

| Concern | Decision |
|---------|----------|
| ORM | Doctrine 3, XML/attribute mapping under each context's `Infrastructure/Persistence` |
| JSONB | `sheet_structure`, `attributes`, snapshot columns mapped to `jsonb` (DBAL type override) |
| Integrity | Partial unique index `ON oracle(scope_system_id) WHERE scope_type='system'`; FKs cascade `campaign → journal/characters` |
| Indexes | `journal(campaign_id, created_at DESC)`; `campaign(player_id)`; `oracle(scope_type, scope_system_id)` |
| Concurrency | Optimistic locking (`version` int) on `GameSystem` aggregate — concurrent admin edits resolve "last saved wins + superseded notice" (Edge Case §8) |
| Migrations | Versioned Doctrine migrations; breaking contract changes additionally carry versioned API migration path (Principle V) |

## State Transitions Summary

| Aggregate | Transition | Guard |
|-----------|-----------|-------|
| GameSystem.status | active ⇄ inactive | deactivate hides from NEW campaigns only (FR-006) |
| Campaign.currentPosition | start → startingStage | system must be active at creation (FR-012) |
| Campaign.currentPosition | stage → stage | edge exists in FlowDefinition, else refusal + legal list (FR-016) |
| Character.reviewStatus | clean → flagged_for_review | stored attributes vs updated SheetStructure mismatch (FR-025) |
| JournalEntry | append-only | immutable history |

## Increment: Admin Campaign-Flows Editor (no schema change)

Zero migrations. `game_systems.flow_definition` keeps its exact jsonb shape —
the structured admin form is a *view* over the same payload:

```text
FlowPayload (unchanged, see RulesetJsonMapper phpdoc)
├── stages:        list<{name: string, guidance: string}>   (≥2, unique non-empty names)
├── starting_stage: string                                  (must be one of the stage names)
└── transitions:   list<{from: string, to: string}>         (both must reference existing stages)
```

Form binding contract (`Rulesets/Infrastructure/Admin/Form/FlowDefinitionType`):
child field names equal the payload keys above, so Symfony maps the stored array
directly; submissions normalize back to the identical shape before
`UpdateFlowDefinitionHandler` applies occupancy guards (FR-005) and optimistic-lock
supersede detection. Creation path additionally validates the whole payload
(including transitions) through `FlowFactory::fromPayload` before insert.
