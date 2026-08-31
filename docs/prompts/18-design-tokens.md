# 18 · Build the design foundations

Wave 6 · after `17-design-canvas` · branch `design-tokens` · ~1 d · fixes **C4**

<context>
Lone Wolf is a multi-system solo-TTRPG assistant; the player app is Next.js (App Router,
TypeScript strict) with four runtime dependencies: `next`, `react`, `react-dom` and
`@tanstack/react-query`. There is currently no CSS in the project at all — 51 inline
`style={{…}}` objects, six hardcoded hex values, no tokens, no dark mode, no responsive rules and
no `public/` directory.

Read before changing anything:
- `docs/audit/03-design.md` §3.3 Phase 1 — the plan this prompt implements
- The canvas produced by `docs/prompts/17-design-canvas.md` — the token values come from there
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
</context>

<preconditions>
`17-design-canvas.md` must have run. Its token set is the input to this prompt; without it you
would be inventing a palette, which is exactly the failure mode the canvas exists to prevent.

    docker compose up -d --build
    docker compose exec php bin/console app:seed:demo

Confirm `npm run test`, `npm run typecheck`, `npm run lint` and `npm run test:e2e` are green
before you start — this change touches every component and you need a clean baseline.
</preconditions>

<problem>
Foundations, before any visual redesign. Three things are missing and one is a shipping defect.

**No stylesheet at all.** `frontend/src/app/layout.tsx` imports none — the Next.js default
`import './globals.css'` line was removed and no file replaced it. Typography is set per-page with
`fontFamily: 'system-ui'`, repeated verbatim in seven files. There is no `next/font`, no
`viewport` export, no favicon and no `public/` directory.

**No token layer, so nothing is themeable.** Six raw hex values, no CSS custom properties, no
`success` / `warning` / `primary` / `background` / `surface` / `foreground` colour of any kind.
Dark mode is impossible without one.

**Duplication in place of a system.** The card idiom
`{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem' }` appears verbatim in
`campaigns/page.tsx`, `campaigns/new/page.tsx`, `StagePanel`, `DiceRollerWidget` and
`OracleDrawer`. The page shell `<main style={{ fontFamily: 'system-ui', maxWidth: 640, margin:
'3rem auto' }}>` is copied into five files.

**C4 — test scaffolding renders to real users.** `DiceRollerWidget.tsx:57` renders
`<p>Dice roller closed.</p>` and `OracleDrawer.tsx:56` renders `<p>Oracles drawer closed.</p>`
instead of returning `null`. Both are visible on the live console. (If prompt
`16-cleanup-sweep.md` has already run, this is fixed — check before redoing it.)
</problem>

<instructions>
1. Confirm the current state: `grep -rc "style={{" frontend/src`, `find frontend/src -name '*.css'`,
   `grep -rn "className" frontend/src`. If CSS already exists, stop and report — someone has
   started this work.

2. Create `frontend/src/app/globals.css` and import it in `layout.tsx`. Define a `:root` token
   layer using the canvas's values: `--color-bg`, `--color-surface`, `--color-fg`,
   `--color-muted`, `--color-border`, `--color-accent`, `--color-danger`, `--color-success`; a
   type scale; a spacing scale; `--radius-sm` / `--radius-md`; `--shadow-sm` / `--shadow-md`.

3. Implement dark mode as a pure token swap, and get the structure right — this is the most
   common way theming breaks:
   - the **complete** light palette on bare `:root`
   - only the tokens redefined under `@media (prefers-color-scheme: dark)`
   - and again under an explicit `[data-theme="dark"]` selector, so a future toggle wins in both
     directions
   - `color-scheme` set on `<html>`

   **Never give a colour its only definition inside a media or attribute block** — a token defined
   only there is undefined in the un-stamped default state, and the page renders one theme's text
   on the other theme's ground.

4. Add `next/font` with one text face and one display face, each with a real fallback stack.
   Delete all seven `fontFamily: 'system-ui'` repetitions.

5. Add a `viewport` export to `layout.tsx` and a favicon in `public/`.

6. Fix C4: return `null` from both components when closed, and update the two Vitest cases that
   assert on the placeholder text so they assert its absence instead.

7. Convert the 51 inline style objects to CSS Modules per component, mapping every hardcoded value
   onto a token. **Change no layout and no behaviour in this step** — this is a mechanical
   translation, and keeping it mechanical is what makes it reviewable. The visual redesign is
   prompt 19.
</instructions>

<constraints>
- **Do not remove or weaken a single `aria-label`, `role`, `aria-busy`, or `<label htmlFor>`
  association.** The E2E suite selects entirely by role and label, and the app's accessibility is
  its strongest quality. Every one of those tests must stay green, unmodified.
- Do not add Tailwind, a CSS-in-JS library, or a component library. `docs/audit/03-design.md` §3.3
  Phase 1 records the decision — plain CSS with custom properties plus CSS Modules — and the
  reasoning. If you want to revisit it, report the argument rather than acting on it.
- Do not restructure components, rename props, or change any component's public interface. Prompt
  19 builds the primitives; this prompt only relocates styling.
- Do not redesign anything. Same layout, same information hierarchy, better foundations.
- Do not touch backend code.
</constraints>

<acceptance_criteria>
    npm run test && npm run typecheck && npm run lint
    npm run test:e2e
    # expected: all green, with NO changes to the E2E selectors

    grep -rc "style={{" frontend/src
    # expected: 0

    grep -rn "#b00020\|#ccc\|#555\|#eee\|#ddd\|#999" frontend/src
    # expected: only inside globals.css, as token definitions

Manually, in a browser:
- the app renders correctly in light mode, dark mode, **and** with the OS set to no preference
- switching the OS theme changes the page without a reload and without unreadable text
- no "closed." text appears anywhere on the console
- the page has a favicon and a title
- at 375px wide the layout is usable — not yet responsive by design, but not broken

Take before/after screenshots of the console in both themes and include them in the PR.
</acceptance_criteria>

<completion>
Branch `design-tokens` off an updated `master`. Commit atomically with short imperative subjects;
the mechanical style conversion (step 7) should be its own commit, separate from the token layer,
so a reviewer can read them independently.

Before finishing, run and report `npm run test`, `npm run typecheck`, `npm run lint`,
`npm run test:e2e` and `make test`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — in particular, do not adjust an E2E selector to accommodate a markup change; if a
selector breaks, the markup changed more than it should have. Do not create or push git remotes.

Report: the token set as implemented, any place the canvas's design could not be expressed in
tokens, and confirmation that no accessibility attribute was removed.
</completion>
