<!--
=== SYNC IMPACT REPORT ===
Version change: (unratified scaffold) → 1.0.0
Modified principles: N/A (initial ratification)
Added sections:
  - Core Principles I–VI:
      I. Hexagonal Architecture (Ports and Adapters)
      II. Domain-Driven Design Bounded Contexts
      III. Strict Typing and SOLID Code Quality
      IV. Testing Discipline (NON-NEGOTIABLE)
      V. Contract-First Decoupled API
      VI. Documentation Parity
  - Technology Stack and Platform Constraints
  - Development Workflow and Quality Gates
  - Governance
Removed sections: None (template placeholder comments replaced)
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

The React/Next.js frontend and the Symfony backend MUST remain entirely
decoupled, communicating exclusively through the defined API contract. The API
MUST be RESTful (implemented with API Platform or equivalent) and MUST be
documented via OpenAPI/Swagger. Direct database access, session sharing, or
server-side templating between frontend and backend is prohibited. Breaking
contract changes MUST introduce an explicit versioned migration path.

*Rationale*: A stable, machine-readable contract lets both stacks evolve and
deploy independently and serves as the single source of truth for integration.

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

**Version**: 1.0.0 | **Ratified**: 2026-08-21 | **Last Amended**: 2026-08-21
