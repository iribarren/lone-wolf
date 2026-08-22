/constitution

Project Name: Lone Wolf (Solo TTRPG Assistant)

TECH STACK:
- Backend: PHP 8.3+, Symfony LTS
- Frontend: React with Next.js
- Database: PostgreSQL.
- API: RESTful or API Platform (OpenAPI/Swagger documented).
- Admin: EasyAdminBundle (for Symfony backoffice).

ARCHITECTURE & PATTERNS (STRICTLY ENFORCED):
1. Hexagonal Architecture (Ports and Adapters):
   - Domain Layer: Contains zero dependencies on Symfony, Doctrine, or any external libraries. Strictly PHP 8.3 native types, Entities, Value Objects, and Domain Events.
   - Application Layer: Contains Use Cases/Command Handlers. Coordinates between the Domain and Infrastructure. Uses Ports (Interfaces).
   - Infrastructure Layer: Contains Doctrine repositories, API Controllers, EasyAdmin dashboards, and external service adapters.
2. Domain-Driven Design (DDD):
   - Organize code by Bounded Contexts (e.g., Campaign, Character, Oracle, Journal, Scenes), NOT by technical concern (no global `Entity` or `Controller` folders).
3. Code Quality:
   - SOLID principles strictly followed.
   - Strict typing enabled (`declare(strict_types=1);`).
   - Write fully testable code. Use PHPUnit for Domain/Application layers and Behat for E2E tests.
4. Documentation:
    - Every feature MUST be documented
    - Every time a new feature is added, or updated, the documentation must reflect that change

COMMUNICATION:
- The Frontend and Backend must remain entirely decoupled, communicating exclusively via the defined API contract.

/specify

Product Vision:
"Lone Wolf" is a digital assistant for Solo TTRPG play. It replaces notebooks and PDFs with an integrated digital environment. Crucially, the app is multi-system (supporting games like D&D, Vampire: The Masquerade, Call of Cthulhu, Ironsworn) and acts as an automated Game Master (GM) by structurally pacing the campaign based on the specific ruleset being played.

BOUNDED CONTEXTS & CORE FEATURES:

1. Ruleset & System Context (Backend Admin + API):
   - Defines the TTRPG systems available in the app.
   - Flow Definition: Each system defines its own "Campaign Flow" (e.g., some games use strict Scene/Sequel structures, others use Acts/Beats, others are sandbox). The backend dictates these structural rules.
   - The Admin Backoffice allows admins to configure new Systems and define their specific Flow state machines.

2. Oracle & Content Context (Backend Admin + Frontend):
   - Contains random tables, event generators, and prompts.
   - Polymorphic/Relational requirement: An Oracle/Table must be able to belong to a specific System (e.g., "D&D 5e Encounter Table") OR be System-Agnostic/Global (e.g., "Generic Weather Generator").

3. Campaign & Flow Context (Frontend + API):
   - Users create Campaigns linked to a specific Ruleset.
   - Structural Journaling: The Journal is not just a text pad; it is structured by the System's Flow. 
   - Pacing Engine: The backend tracks the state of the campaign and actively prompts the user (e.g., "Resolve the current Scene," "Roll a random encounter," "Start a new Beat"). The frontend reflects these prompts to guide the user's play cycle.

4. Character Management Context (Frontend + API):
   - Tracks PC and NPC data. Must be flexible enough to handle different stat blocks depending on the active Ruleset (e.g., JSONB storage in Postgres for flexible character sheets, parsed through strict Domain models based on the system).

5. Mechanics Context:
   - Built-in Dice Roller parsing standard notation ("2d6", "1d20+5").

USER ROLES:
- Admin: Uses the Symfony Backoffice to build Systems, define Flow structures, and create Oracles.
- Player: Uses the Frontend JS app to play. They select a System, and the app guides them through the flow of that specific game while they journal.

/plan

Draft a step-by-step implementation plan for "Lone Wolf" following the Constitution and Specification. Break the work down into the following distinct phases:

Phase 1: Foundation & Monorepo Setup
- Initialize the monorepo structure with `/backend` and `/frontend` directories.
- Setup Docker configuration for PHP 8.3, PostgreSQL, and Node.js.

Phase 2: Backend Core & DDD Scaffolding
- Setup Symfony 7 in `/backend`.
- Create the foundational folder structure for Hexagonal Architecture (Domain, Application, Infrastructure) inside `src/`.
- Setup PHPUnit and PHPStan for strict type checking.

Phase 3: Core Domain & Flow Engine (Backend)
- Implement the `Ruleset` and `Campaign Flow` Domain models. 
- Build the Flow Engine (using State or Strategy patterns) in the Domain/Application layer to manage Campaign pacing (e.g., Acts, Scenes, Beats) without touching the database.
- Implement the `Oracle` Domain models, ensuring logic for polymorphic system-linking (System-Specific vs. System-Agnostic).
- Write comprehensive PHPUnit tests for the Flow Engine and state transitions.

Phase 4: Infrastructure, Persistence & Admin (Backend)
- Implement Doctrine adapters for the Domain interfaces.
- Configure PostgreSQL JSONB mapping for flexible Character/System data.
- Install EasyAdmin. Create CRUD controllers specifically for managing Rulesets, defining Flow states, and managing Oracles.
- Expose the API endpoints (OpenAPI/Swagger documented), focusing on an endpoint that returns a Campaign's current "Flow State" and available next actions.

Phase 5: Frontend Scaffolding & GM UI Implementation
- Initialize the frontend modern JS framework.
- Generate the API client code based on the OpenAPI spec.
- Build the Campaign Interface: Instead of a flat journal, build a structured UI that reacts to the Backend's Flow Engine (e.g., showing GM prompts like "Start Scene" or "Roll Encounter" based on current state).
- Integrate the Dice Roller and Oracle tables floating widgets.