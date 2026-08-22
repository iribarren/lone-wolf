# Dice Context

Stateless dice mechanics logged into the Journal.

## Ubiquitous language

- **DiceNotation**: strictly parsed `NdM±K` with typed refusal reasons (`malformed`,
  `invalid_count`, `invalid_faces`, `out_of_bounds`) raised BEFORE any roll (FR-026/027).
  Bounds: N ∈ [1,50], M ∈ [2,1000], K ∈ [-10000,+10000].
- **DiceRoll**: value object — individual `diceValues`, modifier, total = Σ±modifier, Clock-backed
  timestamp.
- **DiceRoller**: rolls via injected RandomSource; persistence happens only as Journal snapshots.

## Dependency rule

Domain = pure PHP 8.3. Application owns handlers (`RollDice`, `RollAndLog`). Infrastructure provides
API resources only — no tables of its own (Constitution I–II).
