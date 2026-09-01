# Spec Compliance Audit

Feature: `specs/001-solo-ttrpg-assistant` · Audited **2026-08-30** against a running stack
(`docker compose up`, migrated, `app:seed:demo`).

Method: every FR was traced to code, then exercised — over HTTP with `curl`, and through a real
Chromium browser with Playwright for both the player app and the EasyAdmin backoffice. Verdicts
below are what the software *did*, not what the artifacts claim.

## Verdict key

| Verdict | Meaning |
|---|---|
| ✅ **Met** | Works end to end through the intended surface. |
| 🟨 **Met (API only)** | The rule holds in the backend, but no player/admin UI reaches it. |
| 🟧 **Partial** | Works with a material limitation or a workaround. |
| ❌ **Not met** | Does not work through the intended surface. |

---

## 1. Automated gate results

Every gate the project defines was run. **All eight pass.**

| Gate | Command | Result |
|---|---|---|
| PHPUnit `unit` (no kernel) | `phpunit --testsuite unit` | ✅ 117 tests, 128 476 assertions, 0.7 s |
| PHPUnit `integration` | `phpunit --testsuite integration` | ✅ 26 tests, 93 assertions, 9.5 s |
| Behat acceptance | `vendor/bin/behat` | ✅ 19 scenarios, 107 steps |
| PHPStan level max + strict rules | `composer analyze` | ✅ No errors |
| deptrac layer rules | `composer layers` | ✅ 0 violations, 0 skipped, 605 allowed |
| Vitest components | `npm run test` | ✅ 5 files, 24 tests |
| TypeScript + ESLint | `npm run typecheck`, `npm run lint` | ✅ clean |
| Contract drift (Constitution V) | `scripts/check-contract.sh` | ✅ 13 paths, 19 schemas |
| SC-008 journal latency | `scripts/check-journal-performance.sh` | ✅ 500 entries in **0.122 s** (limit 2 s) |
| Playwright smoke | `npm run test:e2e` | ✅ 1/1 |

**This is the audit's most important structural finding.** A fully green board coexists with a
crashing player feature, an unreachable backoffice and a non-functional flow editor. The suites
are not weak in isolation — they are pointed at the wrong seams. Every defect below sits in
exactly the gap the suites do not cover: HTTP **response body shapes**, the **admin UI**, and
**served asset behaviour**.

---

## 2. Functional requirements

### Ruleset & System

| FR | Requirement | Verdict | Evidence |
|---|---|---|---|
| **FR-001** | Admins create/edit/activate/deactivate systems | 🟧 Partial | `SystemCrudController.php` implements all four; verified rendering at `/admin/system`. But the section is unreachable via the documented `/admin` URL (**A1**), and `SetSystemStatusHandler`/`SetSystemStatusCommand` are dead code — status is set through a raw `ChoiceField` instead of the handler. |
| **FR-002** | Exactly one flow per system, named stages | ✅ Met | `FlowDefinition` (`src/Rulesets/Domain/`), 1:1 `flow_definition` JSONB column. `FlowDefinitionTest` covers ≥2-stage, duplicate-name and empty-name invariants. |
| **FR-003** | Admins define legal transitions | ❌ **Not met** | `GameFlowCrudController` renders *Legal transitions* rows, but the **from/to dropdowns contain only the empty option on page load** — no stage is selectable. Browser-verified: `select[…transitions][0][from]` → `options: [""]`. See **A2**. |
| **FR-004** | Exactly one designated starting stage | ❌ **Not met** | Same defect: the *Starting stage* dropdown loads with a single empty option, and the stored value (`"Scene"`) is not pre-selected. The domain enforces the rule correctly; the editor cannot express it. See **A2**. |
| **FR-005** | Refuse flow edits orphaning an occupied stage | ✅ Met | `DoctrineStageOccupancyChecker` + `UpdateFlowDefinitionHandler`; `FlowModificationGuardTest` proves refusal, acceptance and optimistic-lock supersede. Behat: *"An occupied stage cannot be orphaned by a flow edit"*. |
| **FR-006** | Deactivation hides from selection, keeps play working | ✅ Met | `GET /api/systems` filters to active (verified: 5 systems returned, all active); `StartCampaign` refuses inactive systems; `GameSystemStatusTest` proves deactivation preserves flow and sheet. |

### Oracle & Content

| FR | Requirement | Verdict | Evidence |
|---|---|---|---|
| **FR-007** | Admins create tables of weighted textual entries | ❌ **Not met** | `OracleCrudController::configureFields()` yields only `title`, `scopeType`, `scopeSystemId`. Browser-verified form labels: `["Table title","Visibility","Scoped system"]` — **no entries field exists**. `OracleEntriesType.php` is unreferenced dead code. Entries reach the database only via `app:seed:demo` or direct SQL. See **A4**. |
| **FR-008** | Every oracle scoped to one system or globally | ✅ Met | `OracleScope` strategy (`GlobalScope`/`SystemScope`), `scope_type` discriminator, partial unique index `WHERE scope_type='system'`. `OracleScopeTest`, `PersistenceScopingTest`. |
| **FR-009** | Players see own-system ∪ global | ✅ Met | Verified live: a Scene-Sequel campaign returned *Generic Weather* (global) and not *Ladder Encounters* (scoped to Act Ladder). `PersistenceScopingTest`, `ConsultVisibilityTest`. |
| **FR-010** | Consult returns exactly one weighted entry | ✅ Met | `WeightedOracleSelector` with injected `RandomSourceInterface`; unit suite gates distribution over seeded batches (SC-004). Verified live: `{"status":"selected","entry":{…}}`. |
| **FR-011** | Empty table → friendly notice, not an error | ✅ Met | `ConsultationOutcome::emptyTable()` → HTTP 200 `{"status":"empty_table"}`; `OracleDrawer` renders it as `role="status"`. `ConsultVisibilityTest`. |

### Campaign, Flow & Journal

| FR | Requirement | Verdict | Evidence |
|---|---|---|---|
| **FR-012** | Campaigns bound to exactly one active system | ✅ Met | `POST /api/campaigns {gameSystemId}` verified; `StartCampaignHandler` refuses inactive and unknown systems (`HandlersTest`). `game_system_id` is immutable on the aggregate. |
| **FR-013** | New campaign starts on the designated stage, with guidance | ✅ Met | Live: a new Scene-Sequel campaign returned `currentStage: {id:"Scene", guidance:"Open your Scene: …"}` — note this is the *designated* stage, not the first in the list. |
| **FR-014** | Always surface current stage + suggested actions | ✅ Met | `FlowEngine::legalNextStages()` → `suggestedActions[]` on every campaign read; `StagePanel` + `AdvanceActions` render them. |
| **FR-015** | Entries stamped with the stage active at write time | ✅ Met | Denormalised `stage_name` snapshot survives renames. Behat: *"Journal entries keep their original stage stamp after advancing"*. |
| **FR-016** | Only legal transitions; illegal refused with alternatives | ✅ Met | Live 422: `"Cannot advance from \"Scene\" to \"Nowhere\": legal next stages are \"Sequel\"."` plus a structured `legalAlternatives[]`. `FlowEngineTest` (8 cases). |
| **FR-017** | Journal viewable chronologically, grouped by stage | 🟧 Partial | `JournalTimeline` groups by stage and orders newest-first. But the page size is 50 and **the frontend never sends `?cursor=`**, so entries beyond the 50 newest are unreachable in the UI. See **B3**. |
| **FR-018** | All campaign state persists across sessions | ✅ Met | `PersistenceResumeTest` proves stop/resume restores stage + journal exactly; `MicrosecondDateTimeTzImmutableType` keeps same-second writes ordered. |
| **FR-019** | Players access only their own campaigns | ✅ Met | `CampaignOwnershipVoter` + `OwnedCampaignFetcher`; unknown and foreign campaigns both 404. Note `PATCH /characters/{id}` carries no operation-level `CAMPAIGN_OWNER` expression, but `UpdateCharacterHandler` enforces ownership through `OwnedCampaignFetcher` — correct, though **no test covers the foreign-player PATCH path**. |
| **FR-020** | Delete requires confirmation, removes permanently | ✅ Met | Live: `DELETE /campaigns/{id}` → **400**, `?confirm=true` → **204**. `ON DELETE CASCADE` on journal and characters. UI requires typing `DELETE`. |

### Character Management

| FR | Requirement | Verdict | Evidence |
|---|---|---|---|
| **FR-021** | Players create PCs and NPCs | 🟨 Met (API only) | `POST /campaigns/{id}/characters` works. The player app has **no create form** — `campaigns/[id]/page.tsx` issues only a `GET` for characters. See **B2**. |
| **FR-022** | Systems define sheet structure; characters conform | ✅ Met | `SheetStructure`/`SheetSchema` + `AttributeValidator`; `CharacterPanel` renders purely from structure metadata (no hardcoded field names) — proven by a Vitest case using two differently-shaped sheets. |
| **FR-023** | Non-conforming submissions rejected field-by-field | 🟨 Met (API only) | 422 `sheet-validation` with `violations[{field,message}]`; `AttributeValidatorTest` (8 cases), Behat *"Missing and wrong-typed fields are refused field-level"*. No UI path to trigger it (**B2**). |
| **FR-024** | NPCs need a lighter attribute set | ✅ Met | `required_for_pc` / `required_for_npc` per field; Behat *"The lighter NPC set passes where a PC would fail"*. |
| **FR-025** | Drifted characters flagged, never altered | ✅ Met | `DriftDetector` + `review_status`/`drift_issues`; `DriftFlaggingTest` proves a structure bump flags stored data **without touching it**. `⚑ flagged for review` badge with the issue list. |

### Mechanics

| FR | Requirement | Verdict | Evidence |
|---|---|---|---|
| **FR-026** | `NdM±K` with sensible bounds | ✅ Met | Live `2d6+3` → `{diceValues:[4,2], modifier:3, total:9}`. Bounds N ≤ 50, M ≤ 1000, \|K\| ≤ 10000 (`DiceNotationParserTest`). |
| **FR-027** | Invalid notation refused, problem identified, no partial result | ✅ Met | Live `0d6` → 422 `{"reason":"invalid_count","detail":"The die count must be at least 1."}`. A Vitest case explicitly asserts no stale result survives a refusal. |
| **FR-028** | Show every die and the modified total | ✅ Met | `DiceRollerWidget` renders one chip per die plus the total and modifier. |
| **FR-029** | Log a roll into the journal | 🟧 Partial | The roll **is** persisted correctly (Behat: *"The log action appends the roll to the journal"*). But `POST /campaigns/{id}/rolls` returns **IRI strings** where the contract requires embedded `DiceRollResult` and `JournalEntry` objects, and the frontend consequently **crashes the whole app** on *Log to journal*. See **A5**. |

### Platform

| FR | Requirement | Verdict | Evidence |
|---|---|---|---|
| **FR-030** | Only admins reach the backoffice; players see only their own data | 🟧 Partial | Authorization is correct — `access_control ^/admin → ROLE_ADMIN`, `AdminBackofficeLoginTest` proves a `ROLE_PLAYER` is refused. But the backoffice is **unreachable in a browser** at its documented URL (**A1**), so the requirement holds in the negative only. |
| **FR-031** | Both surfaces share one set of definitions | ✅ Met | One PostgreSQL database, one `game_systems` table. Verified live: `app:seed:demo` content appeared in `GET /api/systems` and in `/admin/system` with no duplicate setup. |

**Totals:** ✅ 21 · 🟧 5 · 🟨 2 · ❌ 3 (of 31).

---

## 3. User stories

Judged against each story's own *Independent Test* line in `spec.md`.

| US | Story | Verdict |
|---|---|---|
| **US1** (P1) | Define game systems and their campaign flows | ❌ **Fails its own independent test.** An admin cannot author a complete flow: the starting-stage and transition dropdowns are empty on load (**A2**), and the section is unreachable from `/admin` (**A1**). The domain and the guard rails behind it are sound. |
| **US2** (P2) | Run a guided solo campaign | ✅ **Passes.** Verified end to end in a browser: create → guidance → legal advance → illegal refusal with alternatives → journal → resume → typed-confirmation delete. The strongest story in the build. |
| **US3** (P3) | Author oracles scoped to a system or global | ❌ **Fails.** Scoping works; authoring the actual table content does not exist in the backoffice (**A4**). This is also the only story with **no Behat feature** (§5). |
| **US4** (P3) | Consult oracles during play | ✅ **Passes.** Scoped listing, weighted single-result consultation, save-with-interpretation, friendly empty table — all verified live. |
| **US5** (P4) | Track characters with system-shaped sheets | 🟨 **API only.** Validation, NPC/PC asymmetry and drift flagging are excellent and well tested; there is no UI to create or edit a character (**B2**). |
| **US6** (P5) | Roll dice with standard notation | 🟧 **Partial.** Parsing, bounds, refusals and display are correct and thoroughly tested. Logging a roll crashes the app (**A5**). |

---

## 4. Success criteria

| SC | Criterion | Status |
|---|---|---|
| **SC-001** | New system with a complete flow in < 30 min | ❌ Not achievable through the UI today (**A2**). |
| **SC-002** | App open → first journal entry in < 5 min | ✅ Achieved; measured well under a minute in the browser walkthrough. |
| **SC-003** | ≥ 90 % of transitions anticipated by prompts | ⚪ Not measurable — the spec itself states v1 ships no telemetry and this is a manual playtest rubric. Honestly scoped, but unevidenced. |
| **SC-004** | Oracle distribution within ±5 % over 10 000 consultations | ✅ Gated in the unit suite over seeded batches. Note `quickstart.md` claims "CI additionally asserts" this — **there is no CI**. |
| **SC-005** | 100 % of valid notations correct, 100 % of invalid refused | ✅ Parser matrix + seeded roller batches, all green. |
| **SC-006** | A full session arc using only this application | 🟧 Achievable for the play loop, but not for oracle authoring or character creation. |
| **SC-007** | ≥ 3 materially different flows running simultaneously | ✅ Three seeded systems (loop, ladder-with-dead-end, minimal sandbox) coexist; verified no cross-system interference in oracle scoping or stage guidance. |
| **SC-008** | 500-entry journal loads its latest view in < 2 s | ✅ **0.122 s** measured. Caveat: the UI only ever requests the first page (**B3**), so the measured path is not the full user journey. |

---

## 5. Artifact integrity

The specification documents themselves have drifted from the work.

**Corrupted task IDs.** `tasks.md` lines 140–149 carry IDs `X056`–`X063` instead of `T056`–`T063`.
Commit `211c3e1 "clean up"` renamed them and flipped them from `[ ]` to `[X]` in the same diff.
They no longer match the `T\d+` convention that AGENTS.md's commit rule and every grep depend on,
so US3 is invisible to progress tooling.

**A task marked complete whose deliverable does not exist.** `X063` claims
`backend/features/oracles/authoring.feature`. The file is not on disk. The same `211c3e1` commit
*deleted* `author_oracle_visibility.feature` (32 lines) and `OraclesContext.php` (209 lines).
`OraclesContext.php` was later restored; the authoring feature never was.

**A test removed under a fabricated justification.** The immediately preceding commit:

```
ccf09a6 chore(tasks): remove T063 behat feature —
        Laravel\Lux\Bootstrap\Kernel incompatible with Symfony monorepo
```

`Laravel\Lux\Bootstrap\Kernel` does not exist, in this project or anywhere. The diff removes one
line from `behat.yml`. This is the clearest artifact of an AI agent inventing a reason to delete
a failing test rather than fixing it — and the deleted test is precisely the one that would have
caught **A4** (oracle entries unauthorable).

**Missing task numbers.** T008, T009, T038, T042, T055, T058, T064, T067, T074, T077, T085, T088,
T095 were never issued. Present from the initial generation, so a numbering artifact — but it
makes "all tasks complete" unverifiable by ID sequence.

**Partial FR traceability.** Only 19 of 31 FRs are cited anywhere in `tasks.md`. Uncited:
FR-001, 003, 004, 008, 010, 014, 017, 021, 022, 026, 027, 028. Note the overlap with the failures
above: **FR-003, FR-004, FR-007 and FR-021 are all in the untraced set, and all four are among the
requirements that turned out not to be met.** Lack of traceability and lack of delivery correlate
exactly.

**Untracked increment work.** Everything after Phase 9 — `admin-fix:`, `admin-login:`,
`flow-editor:` — was retro-fitted into `research.md` (R11), `data-model.md`, `quickstart.md` and
`contracts/admin-backoffice.md`, but **no tasks were added to `tasks.md`**. It still reports 100 %
complete while describing less work than was done. `speckit-converge`, the skill that exists for
exactly this, was never used. Every defect in class **A** below originates in that untracked work.

**Contract gate narrower than advertised.** `check-contract.sh` skips `/auth/login` and four
error schemas (`SheetStructure`, `DiceNotationProblem`, `IllegalTransitionProblem`,
`SheetValidationProblem`) — documented honestly in the script header. More significantly, Gate B
matches schemas by *property-subset against any runtime schema*, and neither gate inspects
response **bodies**. That is why **A5** — a live response returning IRI strings where the contract
requires objects — passes the Constitution V gate cleanly.
*Resolved 2026-09-02: `check-contract.sh` gained **Gate C**, which drives the live API with a
fixture player and validates every response body against the contract's schema for that operation.
Gate B now anchors on schema name through an explicit rename map, and the three RFC 7807 schemas
are asserted against live 422 payloads rather than skipped — only `SheetStructure` remains
exempt. Reverting the A5 fix now fails the gate by name. Gate C found three further drifts;
see `02-specs.md` §2.2.6.*

**Constitution tension, unamended.** Principle V prohibits "session sharing"; the `/admin/login`
firewall added in `32e18b7` is session-based. `docs/architecture.md` and
`contracts/admin-backoffice.md` scope it as an admin surface internal to the backend — a
defensible reading, but no amendment was filed and the constitution still reads absolutely.
*Resolved 2026-09-01: Constitution 1.1.0 ratifies that reading — Principle V now scopes the
decoupling clause to the player frontend and exempts the backoffice explicitly. No code changed.*

---

## 6. Defects found

Class **A** — regressions in the un-specified increment work; class **B** — scope never built.

### A1 · The backoffice is unreachable in a browser · **Critical**

`GET http://localhost:8080/admin` → `301` → `http://localhost/admin/` — the `:8080` port is
dropped, landing the admin on whatever is on port 80. Signing in successfully lands on the same
dead URL.

*Root cause.* `backend/public/admin/flow-editor.js` (added by `5459d2b`) creates a physical
`public/admin/` directory. nginx's `try_files $uri $uri/ /index.php…` matches `$uri/`, issues a
directory-style 301, and with `absolute_redirect` at its default the URL is rebuilt from the
container's listen port 80. Contrast `/api`, which has no matching directory and reaches Symfony
normally.

*Verified.* `curl -o /dev/null -w '%{redirect_url}' localhost:8080/admin` → `http://localhost/admin/`,
including when `Host: localhost:8080` is supplied. Playwright: post-login URL
`http://localhost/admin/`, body *"Apache/2.4.66 … Port 80"*.

*Fix.* `absolute_redirect off;` (or `port_in_redirect off;`) in `docker/nginx/default.conf`, or
serve the admin asset from a path that does not shadow the route.

### A2 · Campaign-flow editor dropdowns are empty on load · **Critical**

The *Starting stage*, *from* and *to* selects render with a single empty option. FR-003 and
FR-004 cannot be satisfied through the UI.

*Root cause.* `backend/public/admin/flow-editor.js:44`. The init path calls
`syncSelects(document.body)`; `syncSelects` opens with `var form = fromElement.closest('form'); if (!form) return;`
and `document.body.closest('form')` is always `null`, so the initial population silently no-ops.
The selects only fill once a stage-name input fires `blur` or `change`.

*Verified.* `document.body.closest("form") === null` → `true`; options before blur `[""]`, after
blur `["", "Smoke Quest", "Scene", "Sequel"]`.

*Fix.* Pass the form (or each holder) instead of `document.body` at init.

### A3 · Stage dropdowns offer the game system's name as a stage · **High**

Once populated, the choices were `["", "Smoke Quest", "Scene", "Sequel"]` — *Smoke Quest* is the
system's name, not a stage.

*Root cause.* `stageNames()` selects `input[name$="[name]"]` across the whole form, which also
matches `PersistenceGameSystem[name]`.

*Fix.* Scope the selector to `input[name*="[stages]"][name$="[name]"]`, as the sibling wiring
code in the same file already does.

### A4 · Oracle entries cannot be authored in the backoffice · **Critical**

`OracleCrudController::configureFields()` never yields an `entries` field, though
`persistEntity()`/`updateEntity()` read `$entityInstance->entries()`. `OracleEntriesType.php`
exists but is referenced nowhere in `src/` or `templates/`. Browser-verified form labels:
`["Table title","Visibility","Scoped system"]`.

An empty table is a legal state that degrades gracefully (FR-011), so nothing surfaces the
problem — an admin creates a table, sees no error, and players get "this table is empty".

*Why it survived.* This is exactly what the deleted `authoring.feature` (task `X063`) was
specified to cover.

### A5 · "Log to journal" crashes the player app · **Critical**

*Verified in Chromium:* pressing **Log to journal** replaces the page with *"Application error: a
client-side exception has occurred"*; captured `pageerror: Cannot read properties of undefined
(reading 'map')`.

*Root cause.* Two layers.
1. **Contract violation.** `POST /api/campaigns/{id}/rolls` returns
   `{"roll":"/api/.well-known/genid/…","journalEntry":"/api/campaigns/…/journal"}` — API Platform
   serialised the nested resources as IRIs. `contracts/openapi.yaml:382-383` requires embedded
   `DiceRollResult` and `JournalEntry` objects. (The `journalEntry` IRI even names a different
   campaign id than the one posted to.)
2. **Unguarded consumption.** `campaigns/[id]/page.tsx:195` does `setDiceResult(logged.roll)`;
   `DiceRollerWidget.tsx:106` then calls `result.diceValues.map(...)` on a string.

The roll *is* persisted correctly — the damage is confined to the response shape and the render.

*Why every gate missed it.* Behat asserts on the journal, not the response body. Vitest passes
`DiceRollResultView` props directly, so it never sees the wire shape. The Playwright smoke does
not touch dice. `check-contract.sh` compares paths and schema property sets, never response
bodies.

### B1 · No CI · **Critical (process)**

`.github/` does not exist. AGENTS.md documents six merge gates "no exceptions";
`check-contract.sh` says "CI fails on drift"; `quickstart.md` says "CI additionally asserts"
SC-004. None of it runs anywhere. Every gate is manual — and `ccf09a6`/`211c3e1` demonstrate what
that permits.

### B2 · No character create/edit UI · **High**

FR-021/022/023 and US5 are reachable only over HTTP. `CharacterPanel` is read-only and
`campaigns/[id]/page.tsx` has no character mutation.

### B3 · Journal UI cannot page back · **Medium**

Backend default `limit = 50` with an opaque keyset cursor (`ListJournalEntriesQuery.php:23`);
the frontend never sends `?cursor=` and renders no "load more". SC-008's evidence path is
therefore not the path a user takes.

### B4 · Session lifecycle gaps · **Medium**

`clearSession()` and `hasRole()` are exported from `lib/auth.ts` and called nowhere: no sign-out
control, no role-aware rendering. No 401 interceptor and no refresh, so after the 1-hour TTL
`AuthGate` still considers the user signed in while every query fails.

### Lower severity

| | |
|---|---|
| C1 | `Authorization: Bearer undefined` is sent when `ApiClient` is constructed without `getToken` — `client.ts:84` tests `token !== null` but an absent optional call yields `undefined`. Latent only because `useApiClient` always supplies one. |
| C2 | `/api/auth/login` is absent from the generated `schema.gen.ts` (it is firewall-intercepted, never an API Platform operation), so `AuthGate` reaches it through the `apiPath()` cast — the login request and response are entirely untyped despite Constitution V. |
| C3 | Admin headings leak persistence class names: *"Create PersistenceOracle"*, *"Edit PersistenceGameSystem"*. |
| C4 | `"Dice roller closed."` / `"Oracles drawer closed."` test scaffolding text renders in production (see the design audit). |
| C5 | `migrations/Version20260822232549.php` is an empty auto-generated stub; two later migrations have asymmetric `down()` methods. |
| C6 | `users.roles` is plain `JSON` while every other document column uses the custom `JSONB` type. |
| C7 | `SetSystemStatusHandler` / `SetSystemStatusCommand` have no callers in `src/` or `tests/`. |
| C8 | `.env.dist` ships `ADMIN_EMAIL`/`ADMIN_PASSWORD` commented out, so the README's `app:create-admin` step fails as written. |
| C9 | Root `package.json` declares one dependency, `openrtk`, used nowhere and absent from every spec artifact. |
| C10 | `docs/architecture.md` omits the `Identity` context from its bounded-context list. |
| C11 | Content negotiation defaults to JSON-LD; a client following the contract literally (which documents only `application/json`) gets Hydra envelopes unless it sets `Accept`. |
| C12 | No test covers `PATCH /characters/{id}` from a foreign player, the one campaign-scoped write without an operation-level `CAMPAIGN_OWNER` expression. |

---

## 7. What this adds up to

The **core is genuinely strong.** The domain model is clean, the flow engine is well factored and
well tested, refusals are typed and actionable rather than generic, drift is handled with real
care, and the architectural principles are mechanically enforced with zero suppressions. US2 and
US4 are properly delivered.

Every critical defect is in the same place: **work done after the spec process stopped**. The
`admin-fix:` / `admin-login:` / `flow-editor:` increment shipped three critical regressions
(A1, A2, A3) with no task, no test and no CI. The one story whose executable specification was
deleted (US3) is the one story with a critical hole in it (A4). And the one response shape
neither the contract gate nor any suite inspects is the one that crashes the app (A5).

The recommendations in [01-ai-workflow.md](01-ai-workflow.md) and [02-specs.md](02-specs.md)
target that pattern rather than the individual bugs.
