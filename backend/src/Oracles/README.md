# Oracles Context

Weighted random tables admins author; players consult during play.

## Ubiquitous language

- **Oracle**: titled table of weighted **OracleEntries** (text, weight > 0).
- **OracleScope**: strategy VO — `GlobalScope` visible everywhere, `SystemScope(systemId)` visible
  only to that system's campaigns (FR-008/009).
- **ConsultationOutcome**: result object — `{selected}` | `{emptyTable}` | `{unavailable}` — never an
  exception leak (FR-011).
- **WeightedOracleSelector**: cumulative-weight pick over injected RandomSource.

## Dependency rule

Domain = pure PHP 8.3. Application owns `OracleRepositoryInterface`. Infrastructure provides Doctrine
persistence (scope discriminator + partial unique index) and API resources (Constitution I–II).
