# Requirements Traceability Matrix — 001-solo-ttrpg-assistant

One row per functional requirement in [`spec.md`](spec.md), answering the question the repository
could not previously answer: **which test proves FR-014?**

Derived on **2026-09-01** by reading the repository — `tasks.md` for citations, `backend/tests`,
`backend/features`, `frontend/tests` for proof, and `backend/src` / `frontend/src` for code — and
then reconciled against [`../../docs/audit/spec-compliance.md`](../../docs/audit/spec-compliance.md)
§2, whose verdicts were established on 2026-08-30 against a running stack. The audit is the
cross-check, not the source: where the two disagree the row says so, because a disagreement means
one of them is stale and that is worth knowing.

Gated by `scripts/check-traceability.sh` (merge gate 8, `AGENTS.md`). The gate rejects a
requirement missing from this table, a `NOT-IMPLEMENTED` row with no open task behind it, and any
cited path that is not on disk.

## 1. How to read this

**Status** is exactly one of four, applied in this precedence — the first that is true wins:

| Status | Meaning |
|---|---|
| `NOT-IMPLEMENTED` | No code satisfies the requirement through its intended surface. Must link an open task. |
| `NO-TEST` | Implemented, but no automated test proves it. |
| `NO-TASK` | Implemented and tested, but no task in `tasks.md` cites it. |
| `COVERED` | A task cites it, a test proves it, and code implements it. |

**"Cites"** means a task in `tasks.md` names the requirement, literally (`FR-016`) or through an
explicit inclusive range. Exactly one task uses a range — `T034` writes `FR-002..FR-005`, which is
why `FR-003` and `FR-004` are traced here but invisible to `grep -o 'FR-[0-9]\{3\}' tasks.md`.
Expanding that range into four literal citations would cost nothing and is recommended.

**Test cells** name the file in backticks and the proving case in parentheses. **Implementation
cells** name the files that carry the rule, not every file that touches it. Every backticked path
in this document is checked for existence by the gate.

**The audit column** carries the 2026-08-30 verdict (✅ met · 🟨 met API-only · 🟧 partial ·
❌ not met) and marks a row **⚠ DISAGREES** when this derivation and the audit do not match.
§4 explains each disagreement.

## 2. The matrix

| FR | Requirement | Story | Task(s) | Test(s) | Implementation | Status | Audit 2026-08-30 |
|---|---|---|---|---|---|---|---|
| FR-001 | Admins MUST be able to create, edit, activate, and deactivate game systems presented to players. | US1 | — *(T034 builds the backoffice CRUD but names no FR-001)* | `backend/tests/Unit/Rulesets/GameSystemStatusTest.php` (activation round-trip leaves flow and sheet intact)<br>`backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (index, new and edit pages render; description and sheet edits persist)<br>`backend/features/rulesets/author_system_flow.feature` (authored systems reach the player-facing list)<br>`frontend/tests/e2e/admin.spec.ts` (the documented admin URL reaches the dashboard)<br>**Gap:** no test creates or deactivates a system *through the backoffice form*. | `backend/src/Rulesets/Infrastructure/Admin/SystemCrudController.php`<br>`backend/src/Rulesets/Domain/GameSystem.php`<br>`backend/src/Rulesets/Application/CreateGameSystemHandler.php` | NO-TASK | 🟧 Partial — **⚠ DISAGREES** (§4.1) |
| FR-002 | Each system MUST own exactly one campaign flow definition composed of named stages. | US1 | T034 (`FR-002..FR-005`) | `backend/tests/Unit/Rulesets/FlowDefinitionTest.php` (≥2 stages, unique names, no empty name)<br>`backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (the new-system page renders one flow editor) | `backend/src/Rulesets/Domain/FlowDefinition.php`<br>`backend/src/Rulesets/Domain/GameSystem.php`<br>`backend/src/Rulesets/Infrastructure/Persistence/PersistenceGameSystem.php` | COVERED | ✅ Met |
| FR-003 | Admins MUST be able to define which stage-to-stage movements are legal for a system's flow. | US1 | T034 (`FR-002..FR-005`) | `backend/tests/Unit/Rulesets/FlowDefinitionTest.php` (a transition naming an unknown stage is refused)<br>`backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (adding a transition row persists it and leaves the stages alone)<br>`frontend/tests/e2e/admin.spec.ts` (the from/to dropdowns offer every stage on a cold load) | `backend/src/Rulesets/Infrastructure/Admin/GameFlowCrudController.php`<br>`backend/src/Rulesets/Infrastructure/Admin/Form/FlowTransitionType.php`<br>`backend/src/Rulesets/Infrastructure/Admin/Form/StageNameChoiceType.php`<br>`backend/public/assets/admin-flow-editor.js`<br>`backend/src/Rulesets/Domain/FlowTransition.php` | COVERED | ❌ Not met — **⚠ DISAGREES** (§4.2) |
| FR-004 | Admins MUST designate exactly one stage as the mandatory starting stage for new campaigns. | US1 | T034 (`FR-002..FR-005`) | `backend/tests/Unit/Rulesets/FlowDefinitionTest.php` (a starting stage outside the stage set is refused)<br>`backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (moving the starting stage persists exactly that change)<br>`frontend/tests/e2e/admin.spec.ts` (the starting-stage dropdown pre-selects the stored stage on load) | `backend/src/Rulesets/Domain/FlowDefinition.php`<br>`backend/src/Rulesets/Infrastructure/Admin/Form/FlowDefinitionType.php`<br>`backend/src/Rulesets/Infrastructure/Admin/Form/StageNameChoiceType.php`<br>`backend/public/assets/admin-flow-editor.js` | COVERED | ❌ Not met — **⚠ DISAGREES** (§4.2) |
| FR-005 | The system MUST refuse any flow modification that would leave an existing campaign positioned on a stage that no longer exists. | US1 | T029, T034 | `backend/tests/Integration/Rulesets/FlowModificationGuardTest.php` (removing an occupied stage refused; a stage-preserving edit accepted; a superseded save surfaces as an optimistic-lock failure)<br>`backend/tests/Integration/Rulesets/AdminGameFlowPagesTest.php` (the backoffice refuses with a flash and stores nothing)<br>`backend/features/rulesets/author_system_flow.feature` (an occupied stage cannot be orphaned) | `backend/src/Rulesets/Application/UpdateFlowDefinitionHandler.php`<br>`backend/src/Rulesets/Application/Port/StageOccupancyCheckerInterface.php`<br>`backend/src/Rulesets/Infrastructure/Persistence/DoctrineStageOccupancyChecker.php` | COVERED | ✅ Met |
| FR-006 | Deactivating a system MUST remove it from new-campaign selection while leaving existing campaigns fully playable. | US1 | T028, T034 (`FR-002..FR-005`) | `backend/tests/Unit/Rulesets/GameSystemStatusTest.php` (deactivation keeps flow and sheet intact)<br>`backend/tests/Unit/Campaigns/HandlersTest.php` (starting a campaign on an inactive system is refused) | `backend/src/Rulesets/Domain/GameSystemStatus.php`<br>`backend/src/Rulesets/Application/Query/ListAvailableSystemsQuery.php`<br>`backend/src/Rulesets/Infrastructure/Api/Provider/SystemSummaryProvider.php`<br>`backend/src/Campaigns/Application/StartCampaignHandler.php` | COVERED | ✅ Met |
| FR-007 | Admins MUST be able to create random tables (oracles) consisting of textual result entries, each with a configurable relative likelihood. | US3 | T056 *(T062 builds the CRUD, uncited)* | `backend/tests/Unit/Oracles/OracleAggregateTest.php` (weight > 0, non-blank text, authoring order kept)<br>`backend/tests/Unit/Oracles/Infrastructure/Form/OracleEntriesCollectionTypeTest.php` (the editor round-trips the storage shape)<br>`backend/tests/Integration/Oracles/AdminOracleEntriesTest.php` (the new form exposes the entries editor; weighted rows persist, reopen, reweight and delete)<br>`backend/features/oracles/authoring.feature` (an admin authors a table with its entries through the backoffice) | `backend/src/Oracles/Infrastructure/Admin/OracleCrudController.php`<br>`backend/src/Oracles/Infrastructure/Admin/Form/OracleEntriesCollectionType.php`<br>`backend/src/Oracles/Infrastructure/Admin/Form/OracleEntryType.php`<br>`backend/src/Oracles/Domain/OracleEntry.php` | COVERED | ❌ Not met — **⚠ DISAGREES** (§4.3) |
| FR-008 | Every oracle MUST be scoped either to exactly one game system or globally to all systems. | US3 | T063 | `backend/tests/Unit/Oracles/OracleScopeTest.php` (the global/system visibility predicate matrix)<br>`backend/tests/Integration/Oracles/PersistenceScopingTest.php` (the partial unique index enforces one scoped table per system)<br>`backend/features/oracles/authoring.feature` (global and scoped tables are stored as authored; a second scoped table is refused) | `backend/src/Oracles/Domain/OracleScope.php`<br>`backend/src/Oracles/Domain/GlobalScope.php`<br>`backend/src/Oracles/Domain/SystemScope.php`<br>`backend/src/Oracles/Infrastructure/Persistence/PersistenceOracle.php` | COVERED | ✅ Met |
| FR-009 | For any campaign, players MUST see exactly that system's oracles plus all global ones. | US3 / US4 | T057 | `backend/tests/Integration/Oracles/PersistenceScopingTest.php` (the listing returns global ∪ own-system rows)<br>`backend/tests/Integration/Oracles/ConsultVisibilityTest.php` (a scoped table degrades for a foreign campaign)<br>`backend/features/oracles/consultation.feature` (browsing shows global plus own-system only)<br>`frontend/tests/components/oracles/OracleDrawer.test.tsx` (the drawer renders the scoped list with its scope labels) | `backend/src/Oracles/Application/Query/ListOraclesVisibleToSystemQuery.php`<br>`backend/src/Oracles/Infrastructure/Api/Provider/OracleListProvider.php`<br>`backend/src/Oracles/Infrastructure/Persistence/PersistenceOracleRepository.php` | COVERED | ✅ Met |
| FR-010 | Consulting an oracle MUST return exactly one entry selected by chance in proportion to configured likelihoods. | US3 / US4 | — | *Exactly one entry* is proven: `backend/tests/Integration/Oracles/ConsultVisibilityTest.php`, `backend/features/oracles/consultation.feature` (a consultation answers exactly one weighted result).<br>*In proportion to configured likelihoods* is **not** proven: `backend/tests/Unit/Oracles/WeightedOracleSelectorTest.php` runs its 10 000-draw distribution assertion over three entries **all weighted 1**, so a selector that ignored weights entirely would pass it; its only unequal-weight case (5:1) asserts seed determinism, not distribution. | `backend/src/Oracles/Domain/WeightedOracleSelector.php`<br>`backend/src/Oracles/Application/ConsultOracleHandler.php` | NO-TEST | ✅ Met — **⚠ DISAGREES** (§4.4) |
| FR-011 | Consulting an oracle with no entries MUST produce a friendly empty-table notice rather than an error state. | US4 | T065 | `backend/tests/Unit/Oracles/WeightedOracleSelectorTest.php` (an empty table yields the empty-table outcome)<br>`backend/tests/Integration/Oracles/ConsultVisibilityTest.php` (HTTP 200 with the friendly outcome, not an error)<br>`frontend/tests/components/oracles/OracleDrawer.test.tsx` (the notice renders instead of an error) | `backend/src/Oracles/Domain/ConsultationOutcome.php`<br>`frontend/src/components/oracles/OracleDrawer.tsx` | COVERED | ✅ Met |
| FR-012 | Players MUST be able to create campaigns, each bound to exactly one active game system. | US2 | T040, T046 | `backend/tests/Unit/Campaigns/HandlersTest.php` (an inactive and an unknown system are both refused)<br>`backend/features/campaigns/guided_play.feature` (create over HTTP)<br>`frontend/tests/e2e/play.spec.ts` (the guided play loop starts from creation) | `backend/src/Campaigns/Application/StartCampaignHandler.php`<br>`backend/src/Campaigns/Domain/Campaign.php`<br>`backend/src/Campaigns/Infrastructure/Api/Processor/StartCampaignProcessor.php` | COVERED | ✅ Met |
| FR-013 | A new campaign MUST begin on its system's designated starting stage with guidance visible for that stage. | US2 | T040 | `backend/tests/Unit/Campaigns/HandlersTest.php` (a new campaign positions on the *designated* stage, not the first listed)<br>`frontend/tests/components/campaign/StagePanel.test.tsx` (the stage name and its guidance card render)<br>`frontend/tests/e2e/play.spec.ts` | `backend/src/Campaigns/Application/StartCampaignHandler.php`<br>`backend/src/Rulesets/Domain/FlowStage.php`<br>`frontend/src/components/campaign/StagePanel.tsx` | COVERED | ✅ Met |
| FR-014 | The app MUST always surface the campaign's current stage together with suggested next actions derived from the system's flow (the pacing prompts). | US2 | — | `backend/tests/Unit/Campaigns/FlowEngineTest.php` (guidance offers advance actions derived from the outgoing edges; a terminal stage yields a conclude action instead)<br>`frontend/tests/components/campaign/AdvanceActions.test.tsx` (one control per suggested action; conclude routes separately)<br>`frontend/tests/components/campaign/StagePanel.test.tsx`<br>`frontend/tests/e2e/play.spec.ts` | `backend/src/Campaigns/Domain/FlowEngine.php`<br>`backend/src/Campaigns/Application/GetCampaignStateHandler.php`<br>`backend/src/Campaigns/Application/Query/StageView.php`<br>`frontend/src/components/campaign/AdvanceActions.tsx` | NO-TASK | ✅ Met |
| FR-015 | Journal entries MUST be captured against the flow stage that was active when they were written. | US2 | T040 | `backend/tests/Unit/Campaigns/HandlersTest.php` (an appended entry is stamped with the current stage)<br>`backend/features/campaigns/guided_play.feature` (entries keep their original stage stamp after advancing) | `backend/src/Campaigns/Application/AppendNarrativeEntryHandler.php`<br>`backend/src/Journal/Domain/JournalEntry.php`<br>`backend/src/Journal/Infrastructure/Persistence/PersistenceJournalEntry.php` | COVERED | ✅ Met |
| FR-016 | Stage advancement MUST only follow transitions the system's flow permits; illegal moves MUST be refused with an explanation of legal alternatives. | US2 | T039 | `backend/tests/Unit/Campaigns/FlowEngineTest.php` (8 cases: legal successors only, refusal carries every legal alternative, terminal stages explain there are none)<br>`backend/features/campaigns/guided_play.feature` (an illegal move is refused over HTTP)<br>`frontend/tests/components/campaign/AdvanceActions.test.tsx` (refusal feedback shows the legal alternatives) | `backend/src/Campaigns/Domain/FlowEngine.php`<br>`backend/src/Campaigns/Domain/IllegalStageTransitionException.php`<br>`backend/src/Campaigns/Infrastructure/Api/EventListener/PlayRefusalExceptionListener.php` | COVERED | ✅ Met |
| FR-017 | The journal MUST be viewable chronologically, grouped by flow stage, showing each entry's stage context. | US2 | — | `frontend/tests/components/journal/JournalTimeline.test.tsx` (entries stay in their own stage group across a page seam; the load-more control disappears at the beginning of history)<br>`backend/tests/Integration/Campaigns/PersistenceResumeTest.php` (the keyset cursor walks the whole history)<br>`frontend/tests/e2e/journal-pagination.spec.ts` (the reader pages back to the beginning of a 500-entry journal) | `backend/src/Journal/Application/ListJournalEntriesHandler.php`<br>`backend/src/Journal/Application/Query/ListJournalEntriesQuery.php`<br>`frontend/src/components/journal/JournalTimeline.tsx` | NO-TASK | 🟧 Partial — **⚠ DISAGREES** (§4.5) |
| FR-018 | All campaign state (current stage, journal, characters, logged rolls) MUST persist across sessions for the owning player. | US2 | T041 | `backend/tests/Integration/Campaigns/PersistenceResumeTest.php` (stop and resume restores the exact stage and journal)<br>`backend/features/campaigns/guided_play.feature` (resume assertions)<br>`frontend/tests/e2e/play.spec.ts` | `backend/src/Campaigns/Infrastructure/Persistence/PersistenceCampaignRepository.php`<br>`backend/src/Shared/Infrastructure/Persistence/Types/MicrosecondDateTimeTzImmutableType.php` | COVERED | ✅ Met |
| FR-019 | Each player MUST have access only to their own campaigns; admin-authored content is shared strictly according to its scoping. | US2 | T041, T049 | `backend/tests/Unit/Campaigns/CampaignOwnershipVoterTest.php` (foreign and unknown ids denied identically)<br>`backend/tests/Integration/Campaigns/PersistenceResumeTest.php` (a foreign player never sees someone else's campaign)<br>`backend/tests/Integration/Characters/DriftFlaggingTest.php` (nor their characters)<br>`backend/tests/Unit/Dice/DiceHandlersTest.php` (nor logs into their journal)<br>**Gap:** no test covers `PATCH /characters/{id}` from a foreign player (audit C12). | `backend/src/Campaigns/Infrastructure/Security/CampaignOwnershipVoter.php`<br>`backend/src/Campaigns/Application/OwnedCampaignFetcher.php` | COVERED | ✅ Met |
| FR-020 | Deleting a campaign MUST require explicit confirmation and permanently remove its data. | US2 | T051 | `backend/tests/Unit/Campaigns/HandlersTest.php` (a delete without the confirm flag is refused)<br>`backend/tests/Integration/Campaigns/PersistenceResumeTest.php` (a confirmed delete is irreversible and cascades the journal)<br>**Gap:** no test covers the typed-`DELETE` confirmation in the UI. | `backend/src/Campaigns/Application/DeleteCampaignHandler.php`<br>`backend/src/Campaigns/Application/ConfirmationRequiredException.php`<br>`frontend/src/components/campaign/CampaignSettings.tsx` | COVERED | ✅ Met |
| FR-021 | Players MUST be able to create PCs and NPCs belonging to a specific campaign. | US5 | — | `backend/features/characters/sheets.feature` (a conforming PC is accepted; the lighter NPC set passes)<br>`frontend/tests/components/characters/CharacterForm.test.tsx` (a labelled control per field; the kind control appears on create only)<br>`frontend/tests/e2e/characters.spec.ts` (a player creates a character and edits it) | `backend/src/Characters/Application/CreateCharacterHandler.php`<br>`backend/src/Characters/Domain/CharacterKind.php`<br>`frontend/src/components/characters/CharacterForm.tsx` | NO-TASK | 🟨 Met (API only) — **⚠ DISAGREES** (§4.6) |
| FR-022 | Each system MUST define the expected structure of its character sheets, and every character MUST conform to the structure of its campaign's system. | US5 | — | `backend/tests/Unit/Rulesets/SheetStructureTest.php` (unique keys, select options required, version stamp)<br>`backend/tests/Unit/Characters/AttributeValidatorTest.php` (conforming attributes pass, unknown keys are rejected)<br>`frontend/tests/components/characters/CharacterPanel.test.tsx` (two differently shaped sheets render with no hardcoded field list)<br>`backend/features/characters/sheets.feature` | `backend/src/Rulesets/Domain/SheetStructure.php`<br>`backend/src/Characters/Domain/SheetSchema.php`<br>`backend/src/Characters/Domain/AttributeValidator.php`<br>`backend/src/Characters/Infrastructure/Persistence/DoctrineSheetStructureProvider.php` | NO-TASK | ✅ Met |
| FR-023 | Character submissions that do not conform MUST be rejected with field-level guidance identifying what is wrong. | US5 | T075 | `backend/tests/Unit/Characters/AttributeValidatorTest.php` (every violation carries a field and a message)<br>`backend/features/characters/sheets.feature` (missing and wrong-typed fields are refused field-level)<br>`frontend/tests/components/characters/CharacterForm.test.tsx` (each violation shows against its own field; a refusal naming no field gets its own region)<br>`frontend/tests/e2e/characters.spec.ts` | `backend/src/Characters/Domain/AttributeViolation.php`<br>`backend/src/Characters/Domain/SheetValidationException.php`<br>`backend/src/Characters/Infrastructure/Api/EventListener/SheetValidationProblemListener.php`<br>`frontend/src/components/characters/CharacterForm.tsx` | COVERED | 🟨 Met (API only) — **⚠ DISAGREES** (§4.6) |
| FR-024 | NPCs MUST be trackable with a lighter required attribute set than PCs. | US5 | T075 | `backend/tests/Unit/Characters/AttributeValidatorTest.php` (an NPC skips required-for-PC fields but keeps its own)<br>`backend/features/characters/sheets.feature` (the lighter NPC set passes where a PC would fail)<br>`frontend/tests/components/characters/CharacterForm.test.tsx` (a PC-only field is marked required for a PC and not for an NPC) | `backend/src/Rulesets/Domain/FieldDefinition.php`<br>`backend/src/Characters/Domain/AttributeValidator.php`<br>`backend/src/Characters/Domain/CharacterKind.php` | COVERED | ✅ Met |
| FR-025 | Characters whose stored attributes drift from an updated system structure MUST be flagged for review — never hidden, auto-altered, or silently dropped. | US5 | T076 | `backend/tests/Integration/Characters/DriftFlaggingTest.php` (a structure bump flags stored data without touching it)<br>`backend/features/characters/sheets.feature` (re-saving a drifted character against the new shape clears its flag)<br>`frontend/tests/components/characters/CharacterPanel.test.tsx` (drifted characters render flagged, with their issues) | `backend/src/Characters/Domain/DriftDetector.php`<br>`backend/src/Characters/Domain/ReviewStatus.php`<br>`frontend/src/components/characters/CharacterPanel.tsx` | COVERED | ✅ Met |
| FR-026 | The dice roller MUST accept standard notation NdM optionally followed by +K or −K (e.g., "2d6", "1d20+5", "3d6−2"), with sensible bounds on die count and sides. | US6 | — | `backend/tests/Unit/Dice/DiceNotationParserTest.php` (the valid matrix parses; out-of-bounds counts and faces are refused)<br>`backend/tests/Unit/Dice/DiceRollerTest.php` (canonical notation survives the round trip)<br>`backend/features/dice/notation.feature` (quickstart V6 rows 1–2) | `backend/src/Dice/Domain/DiceNotation.php`<br>`backend/src/Dice/Domain/DiceRoller.php` | NO-TASK | ✅ Met |
| FR-027 | Invalid dice notation MUST be refused with a message identifying the problem; no partial or misleading result may be produced. | US6 | — | `backend/tests/Unit/Dice/DiceNotationParserTest.php` (malformed, `invalid_count`, `invalid_faces`, out-of-bounds each get their own reason, pre-roll)<br>`backend/features/dice/notation.feature` (pathological input refused pre-roll with a specific reason)<br>`frontend/tests/components/dice/DiceRollerWidget.test.tsx` (each reason renders its own notice and never a result — not even a stale one) | `backend/src/Dice/Domain/DiceNotationFailureReason.php`<br>`backend/src/Dice/Domain/InvalidDiceNotationException.php`<br>`backend/src/Dice/Infrastructure/Api/EventListener/DiceNotationProblemListener.php`<br>`frontend/src/components/dice/DiceRollerWidget.tsx` | NO-TASK | ✅ Met |
| FR-028 | Each roll MUST display every individual die value and the final modified total. | US6 | — | `backend/tests/Unit/Dice/DiceRollerTest.php` (batch rolls are mathematically correct: die count, ranges, Σ ± modifier)<br>`backend/features/dice/notation.feature` (both individual dice and their sum are shown)<br>`frontend/tests/components/dice/DiceRollerWidget.test.tsx` (one chip per die beside the modified total, with the modifier) | `backend/src/Dice/Domain/DiceRoll.php`<br>`backend/src/Dice/Infrastructure/Api/DiceRollResource.php`<br>`frontend/src/components/dice/DiceRollerWidget.tsx` | NO-TASK | ✅ Met |
| FR-029 | Players MUST be able to log a roll (notation, dice values, total, timestamp) into the campaign journal. | US6 | T090 | `backend/tests/Unit/Dice/DiceHandlersTest.php` (the log stamps the current stage and persists the snapshot; invalid notation persists nothing)<br>`backend/features/dice/notation.feature` (the log action appends the roll **and answers with both payloads embedded**)<br>`frontend/tests/components/dice/DiceRollerWidget.test.tsx` (logging confirms; a malformed result never crashes the widget) | `backend/src/Dice/Application/RollAndLogHandler.php`<br>`backend/src/Dice/Infrastructure/Api/Processor/RollAndLogProcessor.php`<br>`backend/src/Dice/Infrastructure/Api/DiceRollResource.php`<br>`backend/src/Journal/Domain/RollSnapshot.php` | COVERED | 🟧 Partial — **⚠ DISAGREES** (§4.7) |
| FR-030 | Only authenticated users holding the admin role MAY access the backoffice; players MUST access only player-facing features and their own data. | Platform | T020, T049, T100 | `backend/tests/Integration/Identity/AdminBackofficeLoginTest.php` (5 cases: unauthenticated redirect, wrong credentials, admin sign-in, `ROLE_PLAYER` refused, logout)<br>`backend/tests/Unit/Campaigns/CampaignOwnershipVoterTest.php`<br>`frontend/tests/e2e/admin.spec.ts` (an admin actually reaches the dashboard)<br>`frontend/tests/e2e/session.spec.ts` (sign-out and a rejected token lock the player app) | `backend/config/packages/security.yaml`<br>`backend/src/Identity/Infrastructure/Admin/AdminLoginController.php`<br>`backend/src/Campaigns/Infrastructure/Security/CampaignOwnershipVoter.php` | COVERED | 🟧 Partial — **⚠ DISAGREES** (§4.8) |
| FR-031 | The player app and the admin backoffice MUST operate against the same shared definitions of systems, flows, and oracles — configuration created in the backoffice takes effect in play with no duplicate manual setup. | Platform | T100 | `backend/features/oracles/authoring.feature` (a table authored through the backoffice form is listed and consulted by a player over HTTP, with no second setup step)<br>`backend/features/rulesets/author_system_flow.feature` (authored systems appear in the player-facing list) | `backend/src/Shared/Infrastructure/Console/SeedDemoContentCommand.php`<br>`backend/src/Rulesets/Infrastructure/Persistence/PersistenceGameSystem.php`<br>`backend/src/Rulesets/Infrastructure/Api/Provider/SystemSummaryProvider.php`<br>`backend/src/Rulesets/Infrastructure/Admin/SystemCrudController.php` | COVERED | ✅ Met |

## 3. Summary

| Status | Count | Requirements |
|---|---|---|
| `COVERED` | 22 | FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009, FR-011, FR-012, FR-013, FR-015, FR-016, FR-018, FR-019, FR-020, FR-023, FR-024, FR-025, FR-029, FR-030, FR-031 |
| `NO-TASK` | 8 | FR-001, FR-014, FR-017, FR-021, FR-022, FR-026, FR-027, FR-028 |
| `NO-TEST` | 1 | FR-010 |
| `NOT-IMPLEMENTED` | 0 | — |
| **Total** | **31** | |

**Every requirement that is not `COVERED`**, and what it would take to close it:

1. **FR-010** — `NO-TEST`. The distribution assertion runs over equal weights only; nothing proves
   proportionality. Closing it needs one unequal-weight statistical case (say 5:1 over 10 000
   seeded draws) — which is also the only real evidence behind **SC-004**.
2. **FR-001** — `NO-TASK`. `T034` builds the backoffice CRUD without naming the requirement.
3. **FR-014** — `NO-TASK`. Delivered by `T043`/`T046`/`T051`; the pacing prompts, the product's
   defining promise, are cited by no task.
4. **FR-017** — `NO-TASK`. Delivered by `T046`/`T052` and the pagination increment.
5. **FR-021** — `NO-TASK`. Delivered by `T078`–`T081` and the character-UI increment.
6. **FR-022** — `NO-TASK`. Delivered by `T027`/`T030`/`T078`.
7. **FR-026** — `NO-TASK`. Delivered by `T086`/`T089`.
8. **FR-027** — `NO-TASK`. Delivered by `T086`/`T089`/`T092`.
9. **FR-028** — `NO-TASK`. Delivered by `T087`/`T089`/`T092`.

The eight `NO-TASK` rows are a citation gap, not a delivery gap: every one is implemented and
tested. The repair is to name the requirement in the task that delivers it — and, for `T034`, to
expand `FR-002..FR-005` into four literal citations so a `grep` finds them. That work belongs in
`tasks.md`, not here.

## 4. Reconciliation with the compliance audit

Eight rows disagree with `docs/audit/spec-compliance.md` §2. Seven of the eight are the audit
going stale in the right direction — its own defect list was worked through between 2026-08-30 and
today, and the matrix reflects the repaired code. One is the matrix contradicting the audit on
evidence.

### 4.1 FR-001 — audit 🟧 Partial, matrix `NO-TASK` (partly resolved)

The audit's two reasons were **A1** (the backoffice unreachable at its documented URL) and the
dead `SetSystemStatusHandler`. A1 is fixed — `docker/nginx/default.conf` now sets
`absolute_redirect off`, the shadowing asset moved to `backend/public/assets/`, and
`frontend/tests/e2e/admin.spec.ts` asserts the documented URL reaches the dashboard on its
published port. **The second reason still stands:** `backend/src/Rulesets/Application/SetSystemStatusHandler.php`
and its command have no caller in `src/` or `tests/` — status is set through a raw EasyAdmin
`ChoiceField` (audit C7, still open).

### 4.2 FR-003 / FR-004 — audit ❌ Not met, matrix `COVERED`

Defect **A2** (the flow editor's dropdowns empty on load) and **A3** (the game system's own name
offered as a stage) are fixed. `frontend/tests/e2e/admin.spec.ts` now asserts, without touching a
field first, that every stage is offered, that the stored starting stage is pre-selected, and that
the system name is never offered.

### 4.3 FR-007 — audit ❌ Not met, matrix `COVERED`

Defect **A4** (oracle entries unauthorable) is fixed: `OracleCrudController` yields an entries
editor, `AdminOracleEntriesTest` covers the round trip, and the deleted authoring feature the audit
singled out — `backend/features/oracles/authoring.feature`, task `T063` — is back and drives the
real backoffice form.

### 4.4 FR-010 — audit ✅ Met, matrix `NO-TEST` — **the matrix contradicts the audit**

This is the one disagreement that is not the audit aging. The audit records
*"`WeightedOracleSelector` with injected `RandomSourceInterface`; unit suite gates distribution over
seeded batches (SC-004)"*. Reading the code, **neither half holds**:

- `WeightedOracleSelector` constructs `Random\Randomizer` directly. It takes no
  `RandomSourceInterface`; the shared port is used by `DiceRoller`, not here.
- `WeightedOracleSelectorTest::testDistributionOverConsultations` weights all three entries `1`.
  It proves uniformity, not proportionality — it would pass against a selector that ignored
  weights. The only unequal-weight case asserts that the same seed yields the same answer.

The implementation looks correct (a cumulative-weight pick over `[1..total]`), so this is an
evidence gap rather than a suspected defect — but SC-004's "±5% across 10 000 sample
consultations" is not currently gated by anything that varies a weight. Adding that test is
out of scope here (this prompt produces the map, not the territory) and is the single highest-value
follow-up in this document.

### 4.5 FR-017 — audit 🟧 Partial, matrix `NO-TASK`

Defect **B3** (the UI never sent `?cursor=`, stranding everything past the newest 50 entries) is
fixed: `JournalTimeline` has a load-more control, `journal-pagination.spec.ts` walks a 500-entry
journal to its beginning, and the E2E fixture is seeded in CI before the suite runs.

### 4.6 FR-021 / FR-023 — audit 🟨 Met (API only), matrix `NO-TASK` / `COVERED`

Defect **B2** (no character create/edit UI) is fixed: `CharacterForm.tsx` renders from the sheet
structure, shows violations per field, and `characters.spec.ts` creates and edits a character in a
browser. The requirements are no longer API-only.

### 4.7 FR-029 — audit 🟧 Partial, matrix `COVERED`

Defect **A5** ("Log to journal" crashing the app) is fixed on both layers the audit named:
`POST /api/campaigns/{id}/rolls` now embeds `DiceRollResult` and `JournalEntry` objects instead of
IRI strings — asserted in `backend/features/dice/notation.feature` — and `DiceRollerWidget` refuses
to render a result it cannot read rather than throwing.

### 4.8 FR-030 — audit 🟧 Partial, matrix `COVERED`

The audit's only reservation was that authorization "holds in the negative only" because of **A1**.
With A1 fixed and `admin.spec.ts` asserting an admin actually reaches the dashboard, the positive
case is covered too.

## 5. What this matrix does not claim

- **It is a snapshot, not a live view.** It is true as of the commit that introduced it; the gate
  keeps it structurally honest (no missing requirement, no dangling path), but only a reader keeps
  it *semantically* honest. Re-derive it whenever a story lands.
- **No requirement is untestable as written.** FR-031's "with no duplicate manual setup" is the
  closest — it is a claim about operator effort — but `authoring.feature` pins the testable half:
  content authored in the backoffice reaches a player over HTTP with no second write.
- **Success criteria are out of scope.** SC-001…SC-008 are not rows here; SC-004's evidence gap is
  recorded in §4.4 because it shares its (missing) test with FR-010.
- **`COVERED` means task, test and code — not that the test is exhaustive.** Where a row's coverage
  has a known hole, the row says so: FR-001 (no backoffice create/deactivate test), FR-019 (no
  foreign-player `PATCH /characters/{id}` test), FR-020 (no test for the typed-`DELETE` UI).
