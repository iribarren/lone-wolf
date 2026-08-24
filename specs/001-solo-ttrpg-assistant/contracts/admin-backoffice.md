# Admin Backoffice Contract — Rulesets sections

Increment contract for the EasyAdmin backoffice (server-rendered admin surface;
the player-facing REST contract remains `openapi.yaml`). ROLE_ADMIN session
required for every route below (`/admin/login` gate, FR-030).

## Menu

| Entry | Icon | Route | Notes |
|-------|------|-------|-------|
| Game systems | fa-dice-d20 | `admin_dashboard_system_index` | unchanged |
| Campaign flows | fa-diagram-project | `admin_dashboard_game_flow_index` | **new** |
| Oracles | fa-book-skull | `admin_dashboard_oracle_index` | unchanged |

## Game systems CRUD (`SystemCrudController`)

| Page | Fields shown |
|------|--------------|
| index | name, availability status |
| new | name, description, status + structured campaign flow (stages / starting stage / transitions) |
| edit | name, description, status, character sheet structure |
| detail | name, description, status |

Flow editing moves to the Campaign flows section after creation; the create form
still requires a valid initial flow (≥ 2 stages, one designated start — FR-002/004).
Creating also validates transitions through the domain factory before insert.

## Campaign flows CRUD (`GameFlowCrudController`) — new

- Entity: `PersistenceGameSystem` (1 flow per system — FR-002), so the section
  lists game systems; NEW/DELETE/BATCH actions are disabled.
- index: system name, availability.
- edit: read-only system label + structured flow editor
  (`FlowDefinitionType`): stage rows (name, guidance), starting-stage select,
  transition rows (from/to selects populated from stage names client-side).

### Behavioural guarantees

1. Saving runs `UpdateFlowDefinitionHandler`: illegal structure → danger flash
   with domain message; occupied-stage removal/rename → refusal naming the
   stages (FR-005); superseded concurrent edit → warning flash (edge case §8).
2. Stage-name selects never hard-fail on unknown submitted values; domain
   validation produces the actionable error instead.

## Payload contract (form ⇄ storage)

Identical to `RulesetJsonMapper::flowToPayload()`:

```json
{
  "stages": [{"name": "Scene", "guidance": "…"}],
  "starting_stage": "Scene",
  "transitions": [{"from": "Scene", "to": "Sequel"}]
}
```
