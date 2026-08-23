# Shared Kernel

Cross-context primitives only — the smallest possible shared surface.

## Contents

- **Identifier VOs**: `GameSystemId`, `StageId`, `CampaignId`, `OracleId`, `CharacterId`,
  `JournalEntryId`, `UserId` — typed UUID wrappers preventing primitive obsession across contexts.
- **Ports**: `ClockInterface` (injectable time) and `RandomSourceInterface` (injectable randomness)
  per Constitution IV: tests inject deterministic fakes, production adapters wrap PHP.

## Dependency rule

Shared kernel depends on nothing. Contexts may import identifiers/ports from here; the kernel never
imports from any context (Constitution II).
