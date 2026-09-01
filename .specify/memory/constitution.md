<!--
=== SYNC IMPACT REPORT ===
Version change: 1.0.0 → 1.1.0 (MINOR — materially expanded guidance in Principle V)
Modified principles:
  - V. Contract-First Decoupled API — scoped the decoupling clause to the
    player-facing frontend, and added an explicit carve-out for the EasyAdmin
    backoffice as a server-rendered administrative surface internal to the
    backend, permitted to authenticate by browser session under its own
    contract (`specs/<feature>/contracts/admin-backoffice.md`).
Added sections: None
Removed sections: None
Rationale: The `admin` firewall introduced in `32e18b7` authenticates the
  backoffice with a `form_login` browser session and renders its pages with
  Twig. Principle V as ratified in 1.0.0 prohibited "session sharing" and
  "server-side templating between frontend and backend" without qualification,
  so the document read as forbidding behaviour this repository has always
  intended and shipped. Those prohibitions exist to prevent hidden coupling
  between two independently deployed stacks. The backoffice is not a second
  stack: it runs inside the backend process, is never consumed by the Next.js
  player app, and is governed by its own contract. Leaving the mismatch
  unamended taught every future reader that the constitution is negotiable in
  practice, which is corrosive in a repository whose quality story rests on six
  non-negotiable principles. The behaviour is correct; the document was wrong.
Principle diff (V):
  - "The React/Next.js frontend and the Symfony backend MUST remain entirely
    decoupled" → "The React/Next.js **player** frontend and the Symfony
    backend MUST remain entirely decoupled".
  - "Direct database access, session sharing, or server-side templating between
    frontend and backend is prohibited." → the same three prohibitions, stated
    explicitly as governing the player frontend ↔ backend boundary.
  - ADDED: a paragraph exempting the EasyAdmin backoffice, limited to the
    backoffice and explicitly not extendable to any player-facing surface.
  - UNCHANGED: the RESTful + OpenAPI/Swagger requirement, the versioned
    migration path for breaking contract changes, and every prohibition as it
    applies to the player frontend.
Migration plan: NONE REQUIRED. This amendment ratifies the status quo. No code,
  configuration or security setting changes; no existing behaviour becomes
  non-compliant and none needs to be brought into compliance. Dependent
  artifacts updated in the same change set (Principle VI): README.md,
  docs/architecture.md, specs/001-solo-ttrpg-assistant/plan.md (Constitution
  Check gate row V), specs/001-solo-ttrpg-assistant/research.md (R3), and
  docs/audit/02-specs.md 2.2.7 (finding marked resolved).
Ratification: Lands before any further work depends on it. Supersedes no other
  principle. Ratified date unchanged (2026-08-21); Last Amended 2026-09-01.
Follow-up TODOs: None
=== END SYNC IMPACT REPORT ===
-->

# Lone Wolf Constitution

Lone Wolf is a Solo TTRPG Assistant. This constitution defines the non-negotiable
principles that govern all specification, implementation, and review activity in
this repository.

## Core Principles

### I. Hexagonal Architecture (Ports and Adapters)

The codebase MUST be organized into three concentric layers with dependencies
pointing inward only:

- **Domain Layer**: Pure PHP 8.3 code with ZERO dependencies on Symfony,
  Doctrine, or any external library. Contains entities, value objects, and
  domain events, typed exclusively with PHP native types.
- **Application Layer**: Use cases and command handlers that coordinate between
  the Domain and Infrastructure layers. All collaboration with the outside
  world MUST go through ports (interfaces) owned by this layer or the domain.
- **Infrastructure Layer**: Doctrine repositories, API controllers, EasyAdmin
  dashboards, and external service adapters. These implement ports; they MUST
  never leak framework types inward.

Violations of the dependency rule MUST be treated as build-breaking defects.

*Rationale*: Keeping the domain free of framework coupling preserves long-term
evolvability, enables fast deterministic tests, and prevents vendor lock-in.

### II. Domain-Driven Design Bounded Contexts

Code MUST be organized by Bounded Context (e.g., Campaign, Character, Oracle,
Journal, Scenes), never by technical concern. Global `Entity`, `Repository`, or
`Controller` folders are prohibited. Each bounded context owns its own domain
model, ubiquitous language, ports, and persistence mapping.

*Rationale*: Context-oriented organization keeps a growing TTRPG assistant
comprehensible and allows contexts to evolve independently.

### III. Strict Typing and SOLID Code Quality

Every PHP file MUST declare `declare(strict_types=1);` and MUST use PHP 8.3+
native type declarations on all properties, parameters, and return types.
SOLID principles MUST be strictly followed across all layers. Weak typing,
silent coercions, and god classes are prohibited.

*Rationale*: Strict typing turns whole classes of runtime errors into static
analysis failures and keeps abstractions small and replaceable.

### IV. Testing Discipline (NON-NEGOTIABLE)

Code MUST be fully testable and tested:

- Domain and Application layers MUST be covered by pure PHPUnit tests that run
  without booting Symfony, Doctrine, or any container.
- End-to-end behavior MUST be verified with Behat scenarios expressed in the
  project's ubiquitous language.
- Tests MUST be runnable without network access; collaborators (clock, random,
  oracle services) MUST be injected.
- Unverified code MUST NOT be merged.

*Rationale*: A solo TTRPG assistant makes probabilistic, rules-driven
decisions; correctness can only be sustained through fast unit suites and
executable specifications.

### V. Contract-First Decoupled API

The React/Next.js **player** frontend and the Symfony backend MUST remain
entirely decoupled, communicating exclusively through the defined API contract.
The API MUST be RESTful (implemented with API Platform or equivalent) and MUST
be documented via OpenAPI/Swagger. Between the player frontend and the backend,
direct database access, session sharing, and server-side templating are
prohibited. Breaking contract changes MUST introduce an explicit versioned
migration path.

**Backoffice exemption**: the EasyAdmin backoffice is a server-rendered
administrative surface internal to the backend, not a second stack. It MAY be
rendered server-side with Twig and MAY authenticate by browser session, and it
is governed by its own contract in
`specs/<feature>/contracts/admin-backoffice.md`. This exemption is limited to
the backoffice: no player-facing surface may rely on it, and the player
frontend MUST NOT consume, share a session with, or otherwise depend on the
backoffice.

*Rationale*: A stable, machine-readable contract lets both stacks evolve and
deploy independently and serves as the single source of truth for integration.
The prohibitions exist to prevent hidden coupling between two independently
deployed stacks; an administrative surface that ships inside the backend, runs
in its process, and is never consumed by the player frontend introduces no such
coupling, so holding it to a rule written for a stack boundary it does not
cross would be cargo cult rather than governance.

### VI. Documentation Parity

Every feature MUST be documented. Whenever a new feature is added or an
existing feature is updated, the documentation MUST reflect that change within
the same change set. A pull request whose docs lag behind its behavior MUST be
rejected.

*Rationale*: For a solo developer, documentation is the durable memory of the
project; drift between docs and behavior is treated as a defect.

## Technology Stack and Platform Constraints

The following stack is fixed by this constitution; introducing alternatives
MUST go through the amendment procedure in Governance:

- **Backend**: PHP 8.3+ on Symfony LTS.
- **Frontend**: React with Next.js.
- **Database**: PostgreSQL.
- **API**: RESTful endpoints exposed via API Platform (or equivalent REST
  stack), documented with OpenAPI/Swagger.
- **Admin**: EasyAdminBundle powering the Symfony backoffice.

Framework upgrades within these ecosystems are permitted; replacements of any
stack pillar are not.

## Development Workflow and Quality Gates

Each feature passes through specification, planning, task breakdown, and
implementation. A change set is complete only when ALL gates pass:

1. **Architecture gate**: Layer boundaries and bounded-context organization
   comply with Principles I–II.
2. **Quality gate**: Strict typing and SOLID compliance verified
   (Principle III).
3. **Test gate**: PHPUnit domain/application suite and relevant Behat
   scenarios pass (Principle IV).
4. **Contract gate**: OpenAPI/Swagger documentation matches the exposed API;
   breaking changes carry a versioned migration path (Principle V).
5. **Docs gate**: Feature documentation added or updated (Principle VI).

Complexity MUST be justified against need; speculative abstraction is rejected
in review.

## Governance

- This constitution supersedes all other practices, conventions, and ad-hoc
  decisions in this repository.
- **Amendments** REQUIRE: a written rationale, an explicit diff of affected
  principles, a migration plan for existing code, and ratification before
  merging conflicting work.
- **Versioning policy** (semantic versioning):
  - MAJOR: backward-incompatible governance changes, principle removals or
    redefinitions.
  - MINOR: new principle/section added or materially expanded guidance.
  - PATCH: clarifications, wording, and non-semantic refinements.
- **Compliance review**: Every pull request and review MUST verify adherence
  to all six principles; reviewers cite the violated principle number when
  rejecting work.
- Runtime development guidance lives outside this document; this file holds
  only stable, ratified rules.

**Version**: 1.1.0 | **Ratified**: 2026-08-21 | **Last Amended**: 2026-09-01
