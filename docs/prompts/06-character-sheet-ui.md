# 06 · Add the character sheet UI

Wave 4 · after `01-ci-pipeline` · branch `character-sheet-ui` · ~1 d · fixes audit finding **B2** (high)

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js talking to it **only**
through the generated client in `frontend/src/lib/api/` (Constitution Principle V — raw `fetch`
to API URLs elsewhere is prohibited).

Characters are PCs and NPCs belonging to a campaign. Crucially, **their shape is data**: each
game system defines a sheet structure — a versioned list of fields with a key, label, type
(`text` | `number` | `select`), options, and whether the field is required for PCs and/or NPCs.
A character's attributes are validated field-by-field against its campaign's system structure. If
a system's structure later changes, affected characters are flagged for review and their stored
data is never silently altered.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `docs/audit/spec-compliance.md` §6 finding B2
- `specs/001-solo-ttrpg-assistant/spec.md` — user story US5, requirements FR-021 to FR-025
- `frontend/src/components/characters/CharacterPanel.tsx` — the existing read-only panel
</context>

<preconditions>
The stack must be running and seeded. Two of the seeded systems have sheet structures with
different shapes, which is exactly what you need to test against:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:seed:demo

    # Scene-Sequel Demo -> one field: hp (number, required for PC, not for NPC), structure v2
    # Act Ladder        -> two fields: willpower (number, PC only), discipline (text, PC and NPC)
    # Freeform Sandbox  -> no sheet structure at all

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
**The player app can display characters but cannot create or edit them.**

`frontend/src/app/(play)/campaigns/[id]/page.tsx` issues a single `GET` for
`/api/campaigns/{id}/characters` and passes the result to `CharacterPanel`, which is purely
presentational. There is no create form, no edit form, and no mutation anywhere in the frontend.

Requirements FR-021 ("Players MUST be able to create PCs and NPCs belonging to a specific
campaign"), FR-022 and FR-023 are therefore reachable only by hand-crafting HTTP requests, and
user story US5 is delivered API-only.

This is missing scope, not a bug. **The backend is complete, correct and well tested** — the
audit rated the validation, PC/NPC asymmetry and drift handling as among the best work in the
codebase. Do not rebuild any of it.
</problem>

<pattern>
**The API you are consuming, exactly as it is today:**

`GET /api/campaigns/{campaignId}/characters` returns a list of `CharacterResource`
(`backend/src/Characters/Infrastructure/Api/CharacterResource.php`):

    { id, kind, name, attributes, validatedStructureVersion,
      reviewStatus, driftIssues, structureVersion, structureFields }

`structureFields` is the system's sheet structure travelling alongside the data — that is what
makes dynamic rendering possible without the frontend knowing any field names.

`POST /api/campaigns/{campaignId}/characters` → 201, and
`PATCH /api/characters/{characterId}` → 200, both take `SaveCharacterInput`:

    { kind: "pc" | "npc", name: string, attributes: Record<string, unknown> }

A non-conforming submission answers **422** `application/problem+json` with the type
`.../sheet-validation` and a `violations` array of `{field, message}` — see
`backend/src/Characters/Infrastructure/Api/EventListener/SheetValidationProblemListener.php`.
A system with no sheet structure answers 422 with a "No sheet structure" problem instead.

`kind` is immutable on update; changing it returns a violation on the `kind` field.

**How to render from metadata, and the standard this must meet:** `CharacterPanel.tsx` already
renders sheets purely from `structureFields` with no hardcoded field names, and
`frontend/tests/components/characters/CharacterPanel.test.tsx` proves it with two differently
shaped sheets. Your form must meet the same bar — it must render correctly for Scene-Sequel Demo
and Act Ladder without knowing either system exists.

**How errors are surfaced elsewhere:** `ApiError` in `frontend/src/lib/api/client.ts` already
parses RFC 7807 and exposes `violations`. `frontend/src/components/campaign/AdvanceActions.tsx`
shows the house pattern for turning a structured refusal into UI, and `EntryComposer.tsx` shows
the house pattern for a small form with a pending state and an error region.

**How mutations are written here:** inline `useMutation` in
`frontend/src/app/(play)/campaigns/[id]/page.tsx`, using `useApiClient()` and invalidating or
refetching the relevant query on success. Follow the file's existing conventions; do not
introduce a new data-fetching abstraction for one feature.
</pattern>

<instructions>
1. Read `CharacterPanel.tsx`, its Vitest file, `CharacterResource.php`, `SaveCharacterInput.php`
   and `SheetValidationProblemListener.php`, and confirm the API shapes above still hold. Verify
   by hand with `curl` that a `POST` with a valid payload returns 201 and an invalid one returns
   422 with `violations`. If anything differs, report it and work from what is actually there.

2. Think through the component boundary before writing code. The panel is currently a pure
   presentational component with a tested contract; decide deliberately whether the form is a
   sibling component, a mode of the panel, or a dialog, and keep the existing panel tests passing
   either way.

3. Write the Vitest cases first, against the seeded structures:
   - a form rendered from `structureFields` produces one labelled input per field, with the right
     input type per field type (`number` → numeric input, `select` → a select of its options)
   - the same component renders a differently shaped sheet with no code change
   - a field required for PCs but not NPCs is marked required only when `kind` is `pc`
   - 422 `violations` are rendered against the named fields, not as one blob
   - submitting is disabled while pending, and the form does not clear on a failed submit
   - `kind` is not editable when editing an existing character

4. Build the form component. Rules that matter:
   - **No hardcoded field keys anywhere.** Everything comes from `structureFields`.
   - Send `attributes` as the raw keyed payload. Conformity is judged by the domain validator, not
     by the frontend — client-side checks are a convenience, never the gate.
   - Coerce `number` fields to numbers before sending; the validator rejects numeric strings.
   - When the campaign's system has no sheet structure, show the 422's explanation rather than an
     empty form.

5. Wire create and edit into the console page with `useMutation`, refetching the characters query
   on success. Keep the drift badge and `driftIssues` display working: after a conforming save,
   `reviewStatus` returns to `clean` and the badge must disappear without a reload.

6. Add a Playwright case to `frontend/tests/e2e/` covering the round trip on a seeded system:
   create a PC, see it in the panel, edit it, see the change. Follow the role/label selector style
   of the existing `play.spec.ts` — the app has no CSS classes to select by, and that is
   deliberate.

7. Update the documentation in the same change set (Constitution VI). `docs/functional-guide.md`
   §5.4 states the app has no character form and §8 lists B2 as a known gap; §9 may carry a
   related row. Replace them with a description of the real flow.
</instructions>

<constraints>
- Backend changes are out of scope. If you find a genuine backend defect, report it and stop —
  do not fix it in this PR. (One is already known and deliberately excluded: no test covers
  `PATCH /characters/{id}` from a foreign player. That is item C12 in prompt 16.)
- Do not hand-edit `frontend/src/lib/api/schema.gen.ts`. It is generated by
  `frontend/scripts/generate-api-client.sh`; regenerate it if the contract changes, which it
  should not here.
- Do not add a form library, a validation library, or a component library. The app has four
  runtime dependencies and that is an asset. Plain React state is sufficient for this form.
- Out of scope: visual design. The app is currently unstyled by inline styles only; match the
  surrounding style so prompts 18–19 can restyle everything at once. Do not introduce CSS files
  or classes here.
- Do not weaken the accessibility of the existing panel. Every input needs a real
  `<label htmlFor>`, errors belong in `role="alert"` regions, and the E2E suite selects by role
  and label.
</constraints>

<acceptance_criteria>
    npm run test        # Vitest, including the new cases
    npm run typecheck
    npm run lint
    npm run test:e2e    # including the new character round-trip
    make lint && make test

Manually, in the player app:
- On a **Scene-Sequel Demo** campaign: create a PC named "Vela" with `hp` = 12 → appears in the
  panel. Create an NPC with no `hp` → accepted, because `hp` is not required for NPCs.
- Create a PC with `hp` left blank → refused, with the message shown **against the `hp` field**,
  and nothing persisted.
- Create a PC with `hp` = "twelve" → refused with a type message against `hp`.
- On an **Act Ladder** campaign: the form shows `Willpower` and `Discipline` instead, with no code
  change and no system-specific branch in the source.
- On a **Freeform Sandbox** campaign (no sheet structure): a clear explanation, not a blank form
  or a crash.
- Edit an existing character's name → persists; `kind` cannot be changed.
- Drift: change a system's sheet structure in the backoffice so an existing character is flagged,
  confirm the ⚑ badge appears, then re-save that character conforming to the new structure and
  confirm the badge clears without a page reload.

`grep -rn "'hp'\|\"hp\"\|willpower\|discipline" frontend/src` returns nothing — no system's field
keys are hardcoded in the app.
</acceptance_criteria>

<completion>
Branch `character-sheet-ui` off an updated `master`. Commit atomically with short imperative
subjects; one logical change per commit (`AGENTS.md`: "Task = commit"). Tests land before the
implementation.

Before finishing, run and report `make lint`, `make test`, `npm run typecheck`, `npm run lint`
and `npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, and anything you could not verify.
</completion>
