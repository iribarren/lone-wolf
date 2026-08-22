# Feature Specification: Lone Wolf — Solo TTRPG Digital Assistant

**Feature Branch**: `001-solo-ttrpg-assistant`

**Created**: 2026-08-22

**Status**: Draft

**Input**: User description: Product vision for "Lone Wolf" — a multi-system digital assistant for solo tabletop RPG play that replaces notebooks and PDFs with an integrated environment. Administrators define game systems and their structural campaign flows; the application acts as an automated Game Master by pacing play according to the active system's ruleset. Players create campaigns, journal inside the structure of their chosen game, consult oracles and random tables, track flexible characters, and roll dice — all guided end to end.

## User Scenarios & Testing *(mandatory)*

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE - meaning if you implement just ONE of them,
  you should still have a viable MVP (Minimum Viable Product) that delivers value.

  Assign priorities (P1, P2, P3, etc.) to each story, where P1 is the most critical.
  Think of each story as a standalone slice of functionality that can be:
  - Developed independently
  - Tested independently
  - Deployed independently
  - Demonstrated to users independently
-->

### User Story 1 - Define Game Systems and Their Campaign Flows (Priority: P1)

An administrator registers a new game system in the backoffice (for example, a strict scene/sequel-driven game, an act/beats-driven game, or a freeform sandbox) and structures its **Campaign Flow**: the ordered stages a session moves through, which movements between stages are legal, and which stage new campaigns start in. Once saved, the system becomes available to players.

**Why this priority**: The product's defining promise is that *the platform dictates structural rules per game*. Every other capability — journaling, prompts, oracles, characters — keys off a system's flow. Without at least one configured system nothing can be played, yet this slice alone already delivers a working content foundation for admins.

**Independent Test**: Can be fully tested by creating a system with a multi-stage flow and verifying it appears in the player-facing system list while illegal stage movements are refused.

**Acceptance Scenarios**:

1. **Given** an authenticated admin, **When** they create a game system with a name, description, at least two flow stages, permitted transitions between them, and one designated starting stage, **Then** the system appears as available for players to choose.
2. **Given** a flow where only Stage A → Stage B is permitted, **When** any campaign attempts to move from Stage A to Stage C, **Then** the move is refused with an explanation of legal options.
3. **Given** a system with at least one campaign currently on a stage, **When** the admin tries to remove or rename that stage away, **Then** the change is blocked until campaigns are moved off it.
4. **Given** a published system, **When** the admin deactivates it, **Then** it disappears from new-campaign selection while existing campaigns remain playable.

---

### User Story 2 - Run a Guided Solo Campaign (Priority: P2)

A player picks a game system, creates a campaign, and lands on the starting stage with clear guidance on what to do first ("Open your first Scene"). They write journal entries tied to the current stage, advance along the stages the system allows, receive fresh guidance after each move, stop whenever they like, and resume exactly where they left off.

**Why this priority**: This is the core play loop that replaces notebook-plus-PDF and delivers the automated-GM experience — the main reason a solo player opens the app.

**Independent Test**: Can be fully tested with one configured system: create campaign → receive opening prompt → write entries → advance through a full stage cycle → close and reopen → same stage and journal intact.

**Acceptance Scenarios**:

1. **Given** an available system, **When** a player creates a campaign for it, **Then** the campaign starts on the system's designated starting stage with visible guidance for that stage.
2. **Given** a campaign on a given stage, **When** the player writes a journal entry, **Then** the entry is stored against that stage and appears in the campaign journal.
3. **Given** the current stage has legal next stages, **When** the player advances to one, **Then** the current stage updates and guidance for the new stage is shown.
4. **Given** a closed campaign, **When** the player reopens it later, **Then** the exact stage, journal, and history are restored.
5. **Given** a stage whose flow permits no exit, **When** the player views guidance, **Then** the app explains how to conclude or continue appropriately instead of offering a dead-end advance action.

---

### User Story 3 - Author Oracles Scoped to a System or Global (Priority: P3)

An administrator builds random tables (oracles): titled collections of textual result entries with configurable relative likelihoods. Each oracle either belongs to exactly one game system (e.g., "D&D-style Encounter Table") or is global/system-agnostic (e.g., "Generic Weather Generator") and usable by every system.

**Why this priority**: Oracles supply improvised content solo play depends on; scoping keeps results genre-appropriate per game while shared tables avoid duplicated effort. It precedes in-play consultation but builds on the same backoffice foundations as Story 1.

**Independent Test**: Can be fully tested by creating one global table and one system-specific table and verifying visibility rules across two different systems' campaigns.

**Acceptance Scenarios**:

1. **Given** an oracle scoped to System X with weighted entries, **When** listing oracles available to a System X campaign, **Then** it is included.
2. **Given** the same System X oracle, **When** listing oracles available to a System Y campaign, **Then** it is absent.
3. **Given** a global oracle, **When** listing oracles for campaigns of any system, **Then** it is present.
4. **Given** an oracle entry weighted twice as likely as another, **When** consulted many times, **Then** results occur proportionally to the configured weights within tolerance.

---

### User Story 4 - Consult Oracles During Play (Priority: P3)

During a session the player wonders "what happens next?", picks an applicable oracle, rolls it, instantly receives one result, and can save that result into the journal together with their own interpretation.

**Why this priority**: Turns uncertainty into story without leaving the app — a key part of the automated-GM feel, immediately usable once oracles exist.

**Independent Test**: Can be fully tested by rolling an available oracle repeatedly from within a campaign and saving a result into the journal.

**Acceptance Scenarios**:

1. **Given** a campaign on System X, **When** the player browses oracles during play, **Then** only System X's oracles plus global ones are offered.
2. **Given** any oracle, **When** consulted, **Then** exactly one entry is returned immediately.
3. **Given** a returned result, **When** the player saves it, **Then** a journal entry referencing the oracle name and result is created.
4. **Given** an oracle with no entries, **When** consulted, **Then** a friendly "this table is empty" notice is shown rather than a failure.

---

### User Story 5 - Track Characters with System-Shaped Sheets (Priority: P4)

A player maintains their protagonist (PC) and supporting cast (NPCs) inside a campaign. The attributes a character carries depend on the active system — one game tracks hit points and spell slots, another tracks willpower and disciplines, another just names and bonds. Each system defines its expected character-sheet shape; character data must conform to it.

**Why this priority**: Keeps the mechanical truth of the story beside its narrative; flexibility across wildly different stat layouts is what makes the multi-system claim credible. Valuable, but playable sessions are possible without it.

**Independent Test**: Can be fully tested by creating characters under two systems with different sheet shapes and verifying each accepts its own attributes and rejects mismatched ones.

**Acceptance Scenarios**:

1. **Given** a system-defined sheet structure, **When** a player creates a PC whose attributes conform to it, **Then** the character is saved and displayed with those attributes.
2. **Given** the same structure, **When** attribute data missing required pieces or of the wrong kind is submitted, **Then** it is rejected with field-level guidance.
3. **Given** a campaign, **When** the player adds NPCs, **Then** they can be tracked with a lighter set of required attributes than the PC.
4. **Given** characters exist under different systems, **When** viewing each campaign, **Then** every character renders according to its own system's shape with no cross-system mixing.

---

### User Story 6 - Roll Dice with Standard Notation (Priority: P5)

A player enters standard dice notation such as "2d6" or "1d20+5", sees every individual die plus the modified total, and can log the roll into the journal as a record of what happened.

**Why this priority**: The mechanical glue for checks and tie-breaks; small, self-contained, and useful from the first session, but not differentiating on its own.

**Independent Test**: Can be fully tested by submitting a series of valid and invalid notation strings and verifying results, error messages, and journal logging.

**Acceptance Scenarios**:

1. **Given** input "1d20+5", **When** rolled, **Then** one twenty-sided die value is shown and the total lies between 6 and 25 inclusive.
2. **Given** input "2d6", **When** rolled, **Then** both individual die values and their sum are shown.
3. **Given** malformed input such as "2d" or "d20x", **When** submitted, **Then** the roll is refused with a message identifying the problem and no result is produced.
4. **Given** a completed roll, **When** the player logs it, **Then** notation, dice values, total, and time appear as a journal record.

---

### Edge Cases

- What happens when an admin edits a flow while campaigns are mid-play? Changes must never strand a campaign: removal/renaming of an occupied stage is blocked (see US1-3), and additive changes reach campaigns naturally.
- How does the system handle a deactivated system? Existing campaigns stay fully playable; the system simply cannot be picked for new campaigns.
- What happens when an oracle is emptied or retired while campaigns previously saved its results? Past journal references remain readable with their recorded outcomes; only new consultations fail gracefully.
- How does the system handle character data that no longer matches an updated sheet structure (e.g., an attribute was removed)? Characters are flagged for review and remain editable — data is never silently discarded.
- What happens with pathological dice input ("0d6", "1d0", huge counts)? Out-of-bounds requests are refused with specific messages before any roll occurs.
- How does the journal behave over very long campaigns? History remains browsable chronologically grouped by stage without slowdown noticeable to the player.
- What happens when a player deletes a campaign? Deletion requires explicit confirmation and is clearly communicated as irreversible.
- What happens when two admins edit the same system concurrently? Last saved change wins and the earlier editor is notified their changes were superseded.

## Requirements *(mandatory)*

### Functional Requirements

**Ruleset & System**

- **FR-001**: Admins MUST be able to create, edit, activate, and deactivate game systems presented to players.
- **FR-002**: Each system MUST own exactly one campaign flow definition composed of named stages.
- **FR-003**: Admins MUST be able to define which stage-to-stage movements are legal for a system's flow.
- **FR-004**: Admins MUST designate exactly one stage as the mandatory starting stage for new campaigns.
- **FR-005**: The system MUST refuse any flow modification that would leave an existing campaign positioned on a stage that no longer exists.
- **FR-006**: Deactivating a system MUST remove it from new-campaign selection while leaving existing campaigns fully playable.

**Oracle & Content**

- **FR-007**: Admins MUST be able to create random tables (oracles) consisting of textual result entries, each with a configurable relative likelihood.
- **FR-008**: Every oracle MUST be scoped either to exactly one game system or globally to all systems.
- **FR-009**: For any campaign, players MUST see exactly that system's oracles plus all global ones.
- **FR-010**: Consulting an oracle MUST return exactly one entry selected by chance in proportion to configured likelihoods.
- **FR-011**: Consulting an oracle with no entries MUST produce a friendly empty-table notice rather than an error state.

**Campaign, Flow & Journal**

- **FR-012**: Players MUST be able to create campaigns, each bound to exactly one active game system.
- **FR-013**: A new campaign MUST begin on its system's designated starting stage with guidance visible for that stage.
- **FR-014**: The app MUST always surface the campaign's current stage together with suggested next actions derived from the system's flow (the pacing prompts).
- **FR-015**: Journal entries MUST be captured against the flow stage that was active when they were written.
- **FR-016**: Stage advancement MUST only follow transitions the system's flow permits; illegal moves MUST be refused with an explanation of legal alternatives.
- **FR-017**: The journal MUST be viewable chronologically, grouped by flow stage, showing each entry's stage context.
- **FR-018**: All campaign state (current stage, journal, characters, logged rolls) MUST persist across sessions for the owning player.
- **FR-019**: Each player MUST have access only to their own campaigns; admin-authored content is shared strictly according to its scoping.
- **FR-020**: Deleting a campaign MUST require explicit confirmation and permanently remove its data.

**Character Management**

- **FR-021**: Players MUST be able to create PCs and NPCs belonging to a specific campaign.
- **FR-022**: Each system MUST define the expected structure of its character sheets, and every character MUST conform to the structure of its campaign's system.
- **FR-023**: Character submissions that do not conform MUST be rejected with field-level guidance identifying what is wrong.
- **FR-024**: NPCs MUST be trackable with a lighter required attribute set than PCs.
- **FR-025**: Characters whose stored attributes drift from an updated system structure MUST be flagged for review — never hidden, auto-altered, or silently dropped.

**Mechanics**

- **FR-026**: The dice roller MUST accept standard notation NdM optionally followed by +K or −K (e.g., "2d6", "1d20+5", "3d6−2"), with sensible bounds on die count and sides.
- **FR-027**: Invalid dice notation MUST be refused with a message identifying the problem; no partial or misleading result may be produced.
- **FR-028**: Each roll MUST display every individual die value and the final modified total.
- **FR-029**: Players MUST be able to log a roll (notation, dice values, total, timestamp) into the campaign journal.

**Platform**

- **FR-030**: Only authenticated users holding the admin role MAY access the backoffice; players MUST access only player-facing features and their own data.
- **FR-031**: The player app and the admin backoffice MUST operate against the same shared definitions of systems, flows, and oracles — configuration created in the backoffice takes effect in play with no duplicate manual setup.

### Key Entities *(include if feature involves data)*

- **Game System**: A playable ruleset identified by name and description with an availability status; owns its Campaign Flow and its expected character-sheet structure.
- **Campaign Flow**: A system's ordered set of named stages (e.g., Scene/Sequel, Act/Beat, Free Play), the set of legal transitions among them, and one designated starting stage.
- **Oracle (Random Table)**: A titled collection of weighted textual result entries; scoped either to a single system or globally to all systems.
- **Campaign**: A player's ongoing story bound to exactly one system; remembers its current flow stage and owns all related records.
- **Journal Entry**: A timestamped narrative record tied to a campaign and a specific flow stage; may reference oracle results and dice rolls.
- **Character**: A PC or NPC belonging to a campaign; carries attributes shaped by its system's sheet structure.
- **Dice Roll**: A notation with resulting individual die values and modified total, timestamped and optionally journaled.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin familiar with the backoffice can define a brand-new game system including a complete multi-stage flow in under 30 minutes.
- **SC-002**: A new player can go from opening the app to their first saved journal entry in under 5 minutes.
- **SC-003**: In observed play sessions, at least 90% of stage transitions are correctly anticipated by pacing prompts issued beforehand — the engine reliably tells players what to do next. *(Measured manually against a playtest rubric: a prompt "anticipates" a transition when the player acts on it without consulting external structure. v1 ships no telemetry.)*
- **SC-004**: Oracle output distribution matches configured weights within ±5% across 10,000 sample consultations.
- **SC-005**: 100% of valid dice-notation test inputs produce mathematically correct totals, and 100% of invalid inputs are refused with a helpful message.
- **SC-006**: A player completes a full session arc — create campaign, play through several stages, stop, resume — using only this application, consulting zero external notebooks or PDFs for structure.
- **SC-007**: At least three game systems with materially different flows run simultaneously without cross-system interference; testers identify their correct current stage at least 95% of the time.
- **SC-008**: A campaign journal of 500 entries loads its latest view in under 2 seconds.

## Assumptions

- Standard account registration and login; each player's campaigns are private to them. (Default practice; no special auth requirements stated.)
- Play is online and browser-based; offline support is out of scope for v1.
- The platform ships without bundled content: admins author all systems, flows, and oracles (optionally seeded with examples).
- Solo play focus: one player per campaign; real-time multiplayer collaboration is out of scope for v1.
- The pacing engine advises — it suggests next actions but the player confirms every stage change; there is no forced automation.
- Dice scope for v1 is standard NdM±K notation; exotic mechanics (exploding dice, fudge dice, keep-highest pools) are deferred.
- Third-party licensed game content is entered manually by admins; automated import of copyrighted material is out of scope.
- English-language interface for v1.
