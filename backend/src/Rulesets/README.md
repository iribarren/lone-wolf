# Rulesets Context

Admin-authored game content: game systems and their campaign flows.

## Ubiquitous language

- **GameSystem**: an available-for-play ruleset (name, description, active/inactive status) owning
  exactly one immutable-per-campaign **FlowDefinition** and optionally a **SheetStructure**.
- **FlowDefinition**: named **FlowStages**, legal **FlowTransitions**, one designated starting stage.
- **SheetStructure**: ordered **FieldDefinitions** shaping character sheets of this system.
- **StageOccupancyChecker**: port answered by the Campaigns context — refuses flow edits that would
  strand live campaigns.

## Dependency rule

Domain = pure PHP 8.3, zero framework imports. Application owns ports
(`RulesetRepositoryInterface`, `StageOccupancyCheckerInterface`). Infrastructure implements them
(Doctrine repositories, API Platform resources, EasyAdmin CRUD) and never leaks framework types
inward (Constitution I).
