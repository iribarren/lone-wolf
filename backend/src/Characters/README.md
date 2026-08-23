# Characters Context

PCs and NPCs whose attributes conform to their system's SheetStructure.

## Ubiquitous language

- **Character**: PC or NPC belonging to a campaign; `kind` decides which requirement set applies
  (FR-021/024).
- **AttributesMap**: JSONB-backed key/value map validated field-by-field against the owning system's
  structure (FR-022/023).
- **DriftDetector**: when a stored sheet no longer matches an updated structure, the character is
  **flagged for review** — readable, editable, never silently altered (FR-025).

## Dependency rule

Domain = pure PHP 8.3 (validator + drift detection). Application owns
`CharacterRepositoryInterface`. Infrastructure provides Doctrine JSONB persistence and API resources
(Constitution I–II).
