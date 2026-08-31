# 19 · Build UI primitives and make the drawers real dialogs

Wave 6 · after `18-design-tokens` · branch `ui-primitives` · ~1–2 d

<context>
Lone Wolf is a multi-system solo-TTRPG assistant; the player app is Next.js (App Router,
TypeScript strict) with four runtime dependencies. Prompt `18-design-tokens.md` has established a
token layer, dark mode and CSS Modules, mechanically translating the old inline styles without
changing layout. This prompt builds the reusable primitives and fixes the one genuine
accessibility defect.

Read before changing anything:
- `docs/audit/03-design.md` §3.2 (the product brief) and §3.3 Phase 2 — the plan this implements
- The canvas from `docs/prompts/17-design-canvas.md`
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
</context>

<preconditions>
`18-design-tokens.md` must have landed — the primitives consume its tokens.

    docker compose up -d --build
    docker compose exec php bin/console app:seed:demo

Confirm `npm run test`, `npm run typecheck`, `npm run lint` and `npm run test:e2e` are green
before you start.
</preconditions>

<problem>
**There are no shared primitives, so the same decisions are re-made per file.** After prompt 18
the values come from tokens, but the card idiom still appears independently in
`campaigns/page.tsx`, `campaigns/new/page.tsx`, `StagePanel`, `DiceRollerWidget` and
`OracleDrawer`, the page shell is duplicated across five files, and **every `<button>` in the app
is an unstyled browser default** — there is not a single `background`, `color` or `padding` on any
button except `color: '#b00020'` on the delete link.

**The drawers are neither floating nor dialogs.** `DiceRollerWidget` and `OracleDrawer` are
documented in their own file comments as floating widgets. They are not: both render as static
`<aside>` elements in normal document flow at the bottom of `<main>`. Only the 40px toggle bar in
`campaigns/[id]/page.tsx` is `position: fixed`.

Neither has `role="dialog"`, `aria-modal`, a focus trap, or Escape-to-close. This is the one real
accessibility gap in an app whose accessibility is otherwise its strongest quality — and it also
matters for the product: consulting an oracle is an interruption you return from, so it must not
push the journal you were reading down the page or lose an unsent journal draft behind it.

**Refusals are styled as failures.** `AdvanceActions` renders the legal-alternatives refusal in
the danger colour. "Cannot advance from Scene to Nowhere: legal next stages are Sequel" is the
flow engine doing its job, not an error, and the design brief calls for it to read as
guidance-with-alternatives.
</problem>

<pattern>
The existing components are the specification for the primitives' behaviour — read them before
designing an API. In particular:

- `frontend/src/components/campaign/AdvanceActions.tsx` — how a structured refusal becomes UI, and
  how `pending` and `disabled` are threaded
- `frontend/src/components/journal/EntryComposer.tsx` — the house pattern for a small form with a
  pending state and an error region
- `frontend/src/components/characters/CharacterPanel.tsx` — rendering purely from metadata, and
  the `role="status"` drift badge
- Every component exports its props interface; keep that convention.

`frontend/tests/components/` shows the testing style: behaviour and accessible roles, never
implementation detail. New primitives are tested the same way.
</pattern>

<instructions>
1. Read the eight existing components and both Vitest and Playwright suites. Note every
   `aria-label`, `role` and label association currently in use — you are responsible for all of
   them surviving.

2. Write the failing tests first. For `Drawer` specifically:
   - it exposes `role="dialog"` and `aria-modal="true"` when open
   - focus moves into it on open and is trapped while it is open
   - Escape closes it
   - focus returns to the element that opened it
   - it renders nothing at all when closed

3. Build `frontend/src/components/ui/`: `Button` (primary / secondary / danger / ghost, with a
   pending state), `Input`, `Textarea`, `Card`, `Drawer`, `Badge`, `Banner`
   (info / refusal / danger). Every one consumes tokens from `globals.css` — no hardcoded colours
   anywhere in the primitives.

4. Migrate the existing components onto them:
   - the five card idioms → `<Card>`
   - every raw `<button>` → `<Button>`
   - the page shell duplicated across five files → one layout component
   - `AdvanceActions`' refusal → `<Banner variant="refusal">`, styled as guidance, not failure
   - `CharacterPanel`'s drift flag → `<Badge>`, keeping its `role="status"` and its
     `title={driftIssues.join('; ')}`

5. Make `DiceRollerWidget` and `OracleDrawer` real dialogs via `<Drawer>` — properly overlaying
   the page, with focus management and Escape as above. Verify that opening a drawer while a
   journal entry is half-written does not lose the draft.

6. Add `eslint-plugin-jsx-a11y` to `frontend/eslint.config.mjs` and fix what it reports. If it
   reports something you believe is a false positive, disable that rule explicitly with a comment
   rather than restructuring correct markup to satisfy it.

7. Update `docs/functional-guide.md` §5.4 in the same change set (Constitution VI) — it currently
   describes the drawers as bottom-right toggles.
</instructions>

<constraints>
- **Every existing `aria-label`, `role`, `aria-busy` and label association must survive.** The E2E
  suite selects entirely by role and accessible name; if a Playwright selector breaks, the markup
  changed more than it should have — fix the markup, not the selector.
- Do not add a component library, a headless-UI library, or a focus-trap dependency. The app has
  four runtime dependencies and that is deliberate; a focus trap is ~30 lines and the behaviour is
  better understood when it is yours.
- Do not change any component's data-fetching, props semantics, or the console page's state
  management. This is presentation only.
- Do not redesign the screen layouts. `docs/audit/03-design.md` §3.3 Phase 3 is a separate
  increment; this prompt delivers the primitives that make it possible.
- Do not touch backend code.
</constraints>

<acceptance_criteria>
    npm run test && npm run typecheck && npm run lint
    npm run test:e2e
    make test
    # expected: all green, with NO changes to existing E2E selectors

    grep -rn "border: '1px solid" frontend/src
    # expected: nothing outside components/ui/

Manually, in a browser, in both themes:
- opening the oracle drawer overlays the page rather than pushing content down; the journal stays
  where it was
- Escape closes it; focus returns to the button that opened it
- Tab cycles within the open drawer and does not escape to the page behind
- with a half-written journal entry, opening and closing a drawer leaves the draft intact
- the refusal banner reads as guidance, visibly distinct from the danger styling on the delete
  action
- every button looks like a button, and has a visible keyboard focus state

Screenshots of the console, both themes, drawer open and closed, in the PR.
</acceptance_criteria>

<completion>
Branch `ui-primitives` off an updated `master`. Commit atomically with short imperative subjects;
build the primitives in one commit and migrate consumers in another, so each is reviewable alone.
Tests land before implementation.

Before finishing, run and report `npm run test`, `npm run typecheck`, `npm run lint`,
`npm run test:e2e` and `make test`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass, and never adjust an E2E selector to accommodate a markup change. Do not create or
push git remotes.

Report: the primitives and their APIs, how you implemented the focus trap, any `jsx-a11y` rule you
disabled and why, and confirmation that no accessibility attribute was lost.
</completion>
