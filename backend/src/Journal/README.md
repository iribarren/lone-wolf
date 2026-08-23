# Journal Context

The play chronicle: append-only entries scoped to stages.

## Ubiquitous language

- **JournalEntry**: immutable record belonging to one campaign, stamped with the stage it was
  written in (id + denormalized name snapshot that survives later renames).
- **Kinds**: `narrative` | `oracle_result` | `dice_roll`; snapshots keep oracle titles/result text
  and roll data readable even if referenced content disappears later.

## Dependency rule

Domain = pure PHP 8.3. Application owns `JournalEntryRepositoryInterface`. Infrastructure provides
Doctrine persistence (covering index `(campaign_id, created_at DESC)` for keyset pagination) and
API resources (Constitution I–II).
