# Campaigns Context

Campaign lifecycle and the Flow Engine pacing play.

## Ubiquitous language

- **Campaign**: a player's solo run bound exactly once to a GameSystem; owns its current
  **StagePosition**.
- **FlowEngine**: graph-driven domain service validating transitions against the system's
  FlowDefinition — `legalNextStages`, `assertCanAdvance`, guidance/prompt derivation. Acts,
  Scenes and Beats are data (stage names), never code states.
- **IllegalStageTransitionException**: refusal carrying the legal alternatives (FR-016).
- **Guidance**: engine-derived pacing prompt ("Open your Scene", "Run Sequel", "Close Act").

## Dependency rule

Domain = pure PHP 8.3. Application owns `CampaignRepositoryInterface` and
`FlowDefinitionProviderInterface` (answered by Rulesets infrastructure). Infrastructure provides
Doctrine persistence plus API resources (Constitution I–II).
