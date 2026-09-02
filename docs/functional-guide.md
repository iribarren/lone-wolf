# Lone Wolf — Functional Guide

*What the application does, and how to use it.*

Audience: anyone who wants to **run** Lone Wolf — as a player at the table, or as the
administrator who authors the game systems players use. For architecture and layering see
[architecture.md](architecture.md); for governance see `.specify/memory/constitution.md`.

Verified against a running stack on **2026-08-30**. Where behaviour differs from what the
project's own documents claim, this guide describes what the software actually does and links
to [the audit](audit/README.md).

---

## 1. What Lone Wolf is

Solo tabletop roleplaying normally means juggling a rulebook, a stack of random tables, a
character sheet and a notebook, while also being your own Game Master — the person who decides
*what happens next* and *when a scene is over*.

Lone Wolf replaces that stack with one application, and takes over the **structural** half of
the GM's job:

- It knows the **shape** of the game you are playing — that Ironsworn-style play cycles
  differently from a three-act story game — and walks you through that shape one stage at a time.
- At every stage it tells you what this part of the game is *for*, and offers only the moves the
  system actually allows.
- It stamps everything you write with the stage you were on, so your journal is a structured
  record of the campaign rather than a wall of text.
- It gives you weighted random tables (**oracles**) and a dice roller, and can drop their
  results straight into the journal.

What it deliberately does **not** do: it never advances your campaign for you, never invents
fiction, and never rewrites what you wrote. It advises; you decide.

**Multi-system by design.** "Act", "Scene", "Beat" are not built-in concepts. A game system is
data: a set of named stages, the legal moves between them, and a line of guidance per stage. An
administrator can express D&D-style adventuring, Vampire's Scene/Sequel loop, or a pure sandbox
with the same editor.

---

## 2. Concepts

| Concept | What it means |
|---|---|
| **Game system** | A playable ruleset: a name, a description, an active/inactive status, exactly one campaign flow, and optionally a character-sheet structure. Authored by an admin. |
| **Campaign flow** | The pacing graph for a system: named **stages**, the **legal transitions** between them, and exactly one **starting stage**. Needs at least 2 stages. |
| **Stage** | One position in the flow. Carries **guidance** — the sentence shown to the player while they are parked there. A stage with no outgoing transitions is a **dead end**: the app offers "conclude" instead of "advance". |
| **Sheet structure** | The shape of a system's character sheets: a list of fields, each with a key, label, type (`text`, `number`, `select`), and whether it is required for PCs and/or NPCs. Version-stamped. |
| **Oracle** | A weighted random table. Each entry has text and a positive integer weight. Scoped either **global** (every system sees it) or to **one system**. A system owns at most one scoped table. |
| **Campaign** | One player's playthrough, permanently bound to one game system, sitting on exactly one stage at a time. |
| **Journal entry** | An append-only record stamped with the stage that was active when it was written. Three kinds: `narrative` (you wrote it), `oracle_result` (a saved consultation), `dice_roll` (a logged roll). |
| **Character** | A PC or NPC belonging to a campaign, whose attributes are validated field-by-field against the owning system's sheet structure. |
| **Drift** | When a system's sheet structure changes after a character was saved, the character is **flagged for review** — never silently altered. |

### Two roles

| | **Admin** | **Player** |
|---|---|---|
| Role | `ROLE_ADMIN` | `ROLE_PLAYER` (granted on registration) |
| Surface | EasyAdmin backoffice, server-rendered | Next.js app |
| Auth | Browser session, CSRF-protected form | JWT bearer token, 1-hour TTL, 60 s clock skew |
| Does | Authors systems, flows, sheet structures, oracles | Runs campaigns, journals, consults, rolls |

**Ownership.** Every campaign-scoped operation is gated on the requesting player owning that
campaign. A campaign belonging to someone else and a campaign that does not exist return the
*same* `404` — you cannot probe for other people's games.

---

## 3. Getting started

```bash
cp .env.dist .env                # placeholder secrets — change them before sharing the stack
docker compose up -d --build
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php bin/console app:create-admin
docker compose exec php bin/console app:seed:demo    # optional demo content
```

`app:create-admin` reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` from your `.env`, which `.env.dist`
ships set to `admin@example.com` / `change_me_admin`. Override either with
`--email=you@example.com --password='a-long-passphrase'`, and change both in `.env` before the
stack is reachable by anyone but you.

Then:

| Surface | URL |
|---|---|
| Player app | <http://localhost:3000> |
| Admin backoffice | <http://localhost:8080/admin> — redirects to the sign-in form |
| API + Swagger UI | <http://localhost:8080/api/docs> |
| Health probe | <http://localhost:8080/api/health> → `{"status":"ok"}` |

### The demo content

`app:seed:demo` is idempotent and creates three systems with deliberately different shapes:

| System | Stages | Transitions | Starts on | Character sheet |
|---|---|---|---|---|
| **Scene-Sequel Demo** | Setup, Scene, Sequel | Setup→Scene, Scene→Sequel, Sequel→Setup (a loop) | **Scene** | *Hit points* (number), required of PCs only |
| **Act Ladder** | Act I, Beat, Act II | Act I→Beat, Beat→Act II (a ladder ending in a dead end) | Act I | *Willpower* (number, PCs only), *Discipline* (text, PCs and NPCs) |
| **Freeform Sandbox** | Free Play, Reflection | Free Play→Reflection | Free Play | none — deliberately sheetless |

plus two oracles: **Generic Weather** (global, 4 weighted entries) and **Ladder Encounters**
(scoped to Act Ladder, 3 entries).

Note that Scene-Sequel starts on *Scene*, not *Setup* — the starting stage is a free choice, not
"the first stage in the list".

The three sheets are deliberately different shapes, because the character sheet is rendered from
whatever the system declares (§5.4). Re-running `app:seed:demo` on a stack seeded before the
sheets existed adds them; a system whose sheet an admin has since edited is never overwritten.

---

## 4. Admin guide — authoring the game

### 4.1 Signing in

Go to `http://localhost:8080/admin`, sign in with the account you created. The menu has
three sections: **Game systems**, **Campaign flows**, **Oracles**.

### 4.2 Game systems

`/admin/system` — the system's identity and availability.

| Field | Notes |
|---|---|
| Name | Unique across the installation. |
| Description | Free text shown to players when picking a system. |
| Status | `active` / `inactive`. |
| Campaign flow | Editable **only when creating**. A new system is pre-seeded with a Scene/Sequel skeleton you can adjust. Afterwards, edit it under *Campaign flows*. |
| Sheet structure | Form-only. Defines the character-sheet fields for this system. |

**Deactivating a system** removes it from the players' new-campaign list but leaves every
running campaign on it fully playable. Deactivation is not deletion and never rewrites play.

**Concurrent edits** are guarded by an optimistic-lock `version`. If someone saved between your
page load and your submit, you get a *"your changes were superseded"* flash instead of silently
overwriting them.

### 4.3 Campaign flows

`/admin/game-flow` — an edit-only section (no create, no delete) dedicated to the pacing graph.
For each system you edit:

- **Stages** — a repeatable row of *name* + *guidance*.
- **Starting stage** — a dropdown; exactly one stage.
- **Legal transitions** — repeatable *from* → *to* pairs.

**Saving.** *Save changes* validates the whole graph and then writes it. What you submit is what
is stored: the stage guidance you type is kept, and a save that changes nothing leaves the stored
flow byte-for-byte identical. Every refusal comes back as a red message and writes nothing —

- fewer than two stages, a starting stage that is not one of them, or a transition pointing at a
  stage that no longer exists (FR-002..FR-004);
- an edit that would orphan an occupied stage (below);
- *"your changes were superseded"* if another admin saved between your page load and your submit.

A refusal returns you to the flows list, so reopen the editor and re-apply the change.

> **Known limitation.** Add **one** stage or transition row per save. The editor gives every row
> you add after page load the same field name, so if you add two before saving, only the last one
> is submitted — the other is silently dropped. Save, reopen, add the next.

**The occupied-stage guard.** A flow edit that would remove a stage some campaign is currently
parked on is refused, with a message naming the stage. Running campaigns can never be orphaned
by an authoring mistake. (Renaming is likewise unsafe for occupied stages — journal entries keep
a denormalised copy of the stage name, so history survives renames, but the live campaign
position would not.)

### 4.4 Oracles

`/admin/oracle` — the weighted random tables.

| Field | Notes |
|---|---|
| Table title | Shown to players in the oracle drawer. |
| Visibility | *Global — visible to every system*, or *One game system*. |
| Scoped system | Required when visibility is system-scoped. Each system owns at most one scoped table — enforced by a database constraint, and a friendly message explains the refusal. |
| Result entries | The table's contents: one row per result, each with its text and its weight. |

**Authoring the results.** The *Result entries* editor is a list of rows. **Add a new item**
appends a blank row, and each row has its own delete button. A row is a **Result** — the text a
player sees when the table is consulted, up to 500 characters — and a **Weight**.

Weights are relative likelihoods, not percentages. Three rows weighted 3, 2 and 1 come up half,
a third and a sixth of the time; weighting them 30, 20 and 10 means exactly the same thing. A
weight must be a whole number of 1 or more. Leave it blank and the row counts as 1.

Press *Save changes* to persist the whole table at once. If any row is refused — a weight of 0
or less, an empty result, text beyond 500 characters — nothing is saved and a red banner quotes
the reason, for example *"Oracle entry weights must be positive integers (got 0)."* Fix the row
and save again.

An oracle with no rows at all is legal: players consulting it get a friendly *"this table is
empty"* notice rather than an error, so a table can be created first and filled in later. That
also means an unfinished table gives no warning of its own — if players report an empty oracle,
check its rows here.

The editor appears only on the *New* and *Edit* forms; the list and detail pages show the table's
title and visibility, not its rows.

---

## 5. Player guide — running a campaign

### 5.1 Signing in

Everything under `/campaigns` is behind a sign-in gate. Register with an email and a password of
at least 8 characters; you are signed in immediately and granted `ROLE_PLAYER`. The token is
kept in your browser's local storage.

**Sign out** sits at the top of every page behind the gate. It discards the token and the roles
from local storage and empties the cached API responses, so the next person to sign in on this
browser sees none of your data.

Tokens last one hour and there is no refresh token — a deliberate choice for a single-player app
(research R3). When the hour is up, the next request the app makes is rejected, the session ends
and you land back on the sign-in form with *"Your session expired. Sign in to continue."* Signing
in again picks up exactly where you left off; nothing is lost, because nothing is held in the
browser but the token.

> There is still **no password reset**. An account whose password is lost cannot be recovered.

### 5.2 `/campaigns` — your campaigns

A list of everything you have going, showing each campaign's system, its current stage and when
you last touched it. Empty state: *"Nothing here yet."*

### 5.3 `/campaigns/new` — starting one

Pick from the **active** systems (inactive ones are simply absent) and press **Begin campaign**.
The campaign is created on that system's designated starting stage and you land straight in the
console. A campaign's system can never be changed afterwards.

If no systems are listed you will see *"No active game systems yet. Ask an admin to author one
first."*

### 5.4 `/campaigns/[id]` — the game master console

This is where you play. Everything below lives on one page.

**Current stage.** A card with the stage name and its guidance — for example, on Scene-Sequel's
opening stage: *"Open your Scene: pursue your intent until it resolves or twists."*

**What next?** One button per legal move, labelled *"Advance to &lt;stage&gt;"*. On a dead-end stage
you get a conclude-style prompt instead. Nothing moves without you pressing a button.

*If you attempt an illegal move* (possible through the API, not the buttons) the app refuses and
tells you exactly what was allowed:

> Cannot advance from "Scene" to "Nowhere": legal next stages are "Sequel".

This is presented as guidance, not as a failure — the flow engine enforcing the system's graph is
the feature working. It is visually distinct from a genuine error such as the delete confirmation
in *Campaign settings*, which is the only place the app uses its danger styling.

**Journal.** Your entries newest-first, grouped under the stage they were written on. Entries
keep their original stage stamp forever — advancing to Sequel does not retroactively move
yesterday's Scene notes. Oracle results are tagged *· oracle roll*.

The view opens on the **50 most recent entries**. *Load earlier entries* appends the next 50 to
what is already on screen, as many times as it takes; when it disappears you are looking at the
beginning of the campaign. Writing while older pages are open adds the new entry at the top and
leaves everything you paged back through in place.

**Record what happened at "&lt;stage&gt;".** The composer. Type, press *Add journal entry*; it is
stamped and appended immediately.

**Characters.** A panel rendering each PC/NPC from the system's sheet structure — no field names
are hardcoded, so a Vampire sheet and a D&D sheet both render correctly. Characters whose stored
data no longer matches an updated structure carry a **⚑ flagged for review** badge listing what
drifted; their data is never altered for them.

*Add a character* opens a form built from the same structure: one control per declared field,
typed as the system declared it, and marked *(required)* only for the kind you are creating — on
Scene-Sequel Demo, *Hit points* is required of a PC and optional on an NPC. Nothing is validated
in the browser: a breach is refused by the game itself and the reason appears **under the field
it belongs to** (*"Hit points must be a number."*), with what you typed left in place.

*Edit &lt;name&gt;* reopens the same form on an existing character. The name and the sheet values are
editable; **kind is not** — a PC stays a PC. A save that conforms to the *current* structure also
clears a **⚑** badge, on the spot and with no reload.

> **Sheet shapes are discovered, not assumed.** The API ships a system's sheet shape alongside
> its characters, so a campaign with none yet does not know its own shape: the first save is
> answered with the fields the system requires, the form grows those inputs, and from the second
> character on the full sheet is drawn straight away. On a system with no sheet structure at all
> (the seeded *Freeform Sandbox*), the form says so instead of showing empty boxes.

**Oracles** and **Dice** open from the pair of buttons pinned to the bottom-right of the console.
Both open as **panels over the page**, not as sections appended to it: the journal you were
reading stays exactly where it was, and a half-written entry in the composer is still there when
you close the panel. Each takes keyboard focus when it opens, keeps Tab inside itself while it is
open, closes on **Escape** as well as its *Close* button, and hands focus back to the button you
opened it with.

**Oracles.** Lists the tables visible to this campaign — every global
table plus this system's own. Consulting draws exactly one entry, weighted: in *Generic
Weather*, with weights 3/2/2/1, "Clear skies" comes up three times as often as "A rolling
storm". You can then save the result to your journal, optionally with your own interpretation of
what it means in the fiction. An empty table produces a friendly notice, not an error.

**Dice.** Enter standard notation and roll.

- Accepted: `NdM`, optionally `+K` or `-K` — `1d20`, `2d6+3`, `3d8-1`.
- Bounds: `N` ≤ 50 dice, `M` ≤ 1000 faces, `|K|` ≤ 10000.
- Every individual die is shown as a chip alongside the modified total.
- Bad notation is refused **before** rolling, and the message names the specific problem
  (malformed, die count, face count, out of bounds) rather than saying "invalid".
- **Log to journal** rolls again server-side and records that roll, so what stays on screen is
  exactly what the journal now holds. *"Logged to your journal."* replaces the button and the
  entry appears in the timeline, stamped with your current stage.

**Campaign settings.** A collapsed *danger zone*. Deleting a campaign is irreversible and takes
its journal and characters with it; the delete button stays disabled until you type `DELETE`
exactly.

### 5.5 Stopping and resuming

There is no "save" — everything is persisted as it happens. Close the tab whenever you like;
reopening the campaign restores the exact stage and the full journal.

---

## 6. A worked session

Using the seeded **Scene-Sequel Demo**.

1. **Start.** `/campaigns/new` → *Scene-Sequel Demo* → *Begin campaign*. You land on **Scene**:
   *"Open your Scene: pursue your intent until it resolves or twists."* One action is offered:
   *Advance to Sequel*.
2. **Play the scene.** You decide your character is crossing a frozen river. You want to know
   the weather: open **Oracles**, consult *Generic Weather* → *"Sudden rain hammers the road
   ahead."* Save it to the journal with the interpretation *"The ice will be treacherous."*
3. **Test the ice.** Open **Dice**, roll `2d6+3` → dice `[4, 2]`, total **9**. Write the outcome
   yourself: *"The wolf tests the ice — it holds, barely."* → *Add journal entry*. It is stamped
   **Scene**.
4. **Advance.** Press *Advance to Sequel*. The guidance changes to *"Run your Sequel: react,
   recover, and steer toward the next Scene."* and the only offered move becomes *Advance to
   Setup*.
5. **Reflect.** Write a Sequel entry. It is stamped **Sequel**; your earlier entries are still
   grouped under **Scene**.
6. **Loop.** *Advance to Setup*, frame the next scene, and go round again. Scene-Sequel is a
   cycle, so this campaign never "ends" — Act Ladder, by contrast, terminates at *Act II*, a
   dead end where the app offers a conclude prompt instead of an advance.

---

## 7. API reference for players

Base URL `http://localhost:8080/api`. All endpoints except registration, login, `/health` and
`/docs` require `Authorization: Bearer <token>`.

**Send `Accept: application/json`.** Without it, content negotiation defaults to JSON-LD/Hydra
(`{"member": [...]}`) rather than the plain shapes documented in the contract.

### Auth
| | |
|---|---|
| `POST /auth/register` | `{email, password}` (min 8 chars) → `{token, roles}` |
| `POST /auth/login` | `{email, password}` → `{token, roles}` |

### Systems and campaigns
| | |
|---|---|
| `GET /systems` | Active systems: `{systemId, name, description, startingStage, openingGuidance}` |
| `GET /campaigns` | Your campaigns |
| `POST /campaigns` | `{gameSystemId}` → the campaign state |
| `GET /campaigns/{id}` | `{id, gameSystemId, currentStage: {id, name, guidance, suggestedActions[]}}` |
| `POST /campaigns/{id}/advance` | `{toStageId}` → the new state |
| `DELETE /campaigns/{id}?confirm=true` | `204`. Without `confirm`, `400`. |

### Journal
| | |
|---|---|
| `GET /campaigns/{id}/journal` | `{entries[], nextCursor}`; 50 per page, newest first. `?cursor=` walks back (the *Load earlier entries* control), `?stageId=` filters (no UI yet). |
| `POST /campaigns/{id}/journal` | `{narrative}` → the created entry |

### Oracles
| | |
|---|---|
| `GET /campaigns/{id}/oracles` | `{oracleId, title, scopeType, entryCount}[]` |
| `POST /campaigns/{id}/oracles/{oracleId}/consult` | `{save}` → `{status, entry?, journalEntryId?}`; `status` is `selected`, `empty_table` or `unavailable` |
| `POST /campaigns/{id}/oracles/{oracleId}/save` | `{text, interpretation?}` → `201` |

### Characters
| | |
|---|---|
| `GET /campaigns/{id}/characters` | Cast plus the sheet-structure metadata needed to render it — the metadata travels *per character*, so an empty cast carries none |
| `POST /campaigns/{id}/characters` | `{kind, name, attributes}` → `201` |
| `PATCH /characters/{characterId}` | Revalidated against the *current* structure; a conforming save clears the drift flag. `kind` is immutable. |

### Dice
| | |
|---|---|
| `POST /dice/roll` | `{notation}` → `{notation, diceValues[], modifier, total}` |
| `POST /campaigns/{id}/rolls` | Rolls **and** journals it → `201` with both embedded: `{roll: {…}, journalEntry: {…}}` |

### Errors — RFC 7807 `application/problem+json`

Every refusal carries a machine-readable reason, not just a message.

```jsonc
// Illegal move — 422
{ "type": ".../illegal-stage-transition", "title": "Illegal stage transition", "status": 422,
  "detail": "Cannot advance from \"Scene\" to \"Nowhere\": legal next stages are \"Sequel\".",
  "legalAlternatives": [{ "kind": "advance", "toStageId": "Sequel", "prompt": "Advance to Sequel" }] }

// Bad dice — 422
{ "type": ".../dice-notation", "title": "Invalid dice notation", "status": 422,
  "detail": "The die count must be at least 1.", "reason": "invalid_count" }

// Bad character sheet — 422
{ "type": ".../sheet-validation", "status": 422,
  "violations": [{ "field": "hp", "message": "…" }] }
```

`reason` is one of `malformed`, `invalid_count`, `invalid_faces`, `out_of_bounds`.

---

## 8. Limits and known gaps

**By design**

- One player per campaign; no sharing, co-op or spectating.
- The app never generates prose. Oracles return authored table text; you write the fiction.
- A campaign's system is fixed at creation.
- Journal entries are append-only — no edit, no delete except by deleting the campaign.
- Dice: `N` ≤ 50, `M` ≤ 1000, `|K|` ≤ 10000.

**Defects and gaps** (all verified 2026-08-30; details and severity in [the audit](audit/README.md))

| | |
|---|---|
| B4 | No password reset. (Sign-out and expired-token handling: fixed.) |

**Not built**

Character import/export, campaign export, images or maps, multiple flows per system, oracle
folders or tagging, search across the journal, filtering the journal to one stage (the API's
`?stageId=` has no control yet), mobile-specific layout, offline play.

---

## 9. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| *"No active game systems yet"* | No active system exists. Run `app:seed:demo`, or author one and set its status to `active`. |
| API returns `{"@context": …, "member": […]}` | You did not send `Accept: application/json`; you got JSON-LD. |
| Consulting an oracle says the table is empty | It genuinely has no rows. Open it at `/admin/oracle`, add result entries and save (§4.4). |
| Adding a character says the system *"defines no sheet structure yet"* | The system has no character sheet. Author one at `/admin/system` under *Character sheet structure* (§4.2). |
| A campaign 404s | Either it does not exist or it is not yours; the two are deliberately indistinguishable. |
| Sent back to the sign-in form with *"Your session expired"* | The JWT reached its 1 h TTL; there is no refresh token by design. Sign in again — the app now ends the session cleanly instead of letting every request fail. |

---

## 10. Command reference

```bash
make up            # boot the stack
make down          # stop it
make logs          # tail all service logs
make ps            # service status
make db-migrate    # run Doctrine migrations
make console       # shell into the php container
make test          # PHPUnit unit + integration, Behat, Vitest
make lint          # PHPStan (level max) + deptrac layer rules
make npm CMD="install"

scripts/check-contract.sh              # runtime OpenAPI vs the canonical contract
scripts/check-journal-performance.sh   # 500-entry journal latency evidence
cd frontend && npm run test:e2e        # Playwright smoke (needs the stack up + seeded)

docker compose exec php bin/console app:create-admin              # uses ADMIN_EMAIL / ADMIN_PASSWORD
docker compose exec php bin/console app:create-admin --email=… --password=…   # or override them
docker compose exec php bin/console app:seed:demo
docker compose exec php bin/console app:seed:large-journal --entries=500
```

---

## 11. Glossary

**Advance** — moving a campaign from its current stage to a legally reachable one. Always
player-initiated. · **Chaos**/**oracle** — a weighted random table consulted for an unbiased
answer. · **Dead end** — a stage with no outgoing transitions. · **Drift** — divergence between
a character's stored attributes and its system's current sheet structure. · **Guidance** — the
per-stage sentence telling the player what this part of play is for. · **Keyset pagination** —
the cursor scheme that keeps a long journal fast. · **NdM±K** — standard dice notation: N dice
of M faces, plus or minus K. · **Scope** — whether an oracle is global or bound to one system. ·
**Stage** — one node in a campaign flow. · **Suggested action** — a legal next move offered by
the flow engine.
