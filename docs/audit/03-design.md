# 3 · Design

*There is currently no design work in this project. Here is what to do about it, and how to use
AI tooling to do it.*

---

## 3.1 Where things stand — measured, not impressionistic

| Measure | Value |
|---|---|
| CSS files in the repo (`.css`, `.scss`, `.module.css`) | **0** |
| Occurrences of `className` in `frontend/src` | **0** |
| Inline `style={{…}}` objects | **51** |
| Distinct colour values in the entire app | **6** (`#b00020`, `#ccc`, `#555`, `#eee`, `#ddd`, `#999`) |
| Design tokens / CSS custom properties | 0 |
| `@media` queries / breakpoints | 0 |
| Dark-mode handling | none |
| `public/` directory (favicon, logo, OG image) | does not exist |
| `next/font` usage | none — `fontFamily: 'system-ui'` repeated in 7 files |
| UI library / component primitives | none |
| Styled `<button>` elements | **0** — every button is a raw browser default |

The design system, in full, is one copy-pasted card idiom:

```tsx
style={{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem' }}
```

duplicated verbatim across `campaigns/page.tsx`, `campaigns/new/page.tsx`, `StagePanel`,
`DiceRollerWidget` and `OracleDrawer`, and one page shell:

```tsx
<main style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '3rem auto' }}>
```

copied into five files (with `AuthGate` at `maxWidth: 360` and the landing page at plain
`padding: '3rem'` with no max-width at all).

There is no `success`, `warning`, `primary`, `background`, `surface` or `foreground` colour.
Nothing is themeable. `#b00020` — the error red — is the only deliberate colour decision in the
codebase.

**What the game master console actually looks like today:** an unstyled `h1`, a bordered stage
card, a native `<button>` reading *Advance to Sequel*, a journal heading, a textarea, a disabled
*Add journal entry* button, a `<details>` toggle, and then — rendering in production, visible to
every player — the two lines:

> Dice roller closed.
> Oracles drawer closed.

That is test scaffolding shipped to users (`DiceRollerWidget.tsx:57`, `OracleDrawer.tsx:56`).
Both components render a visible placeholder when closed instead of `null`.

Relatedly, the two "floating drawers" are documented as floating and are not: they render as
static `<aside>` elements in normal document flow at the bottom of `<main>`. Only the 40 px
toggle bar is `position: fixed`.

### The one thing that is genuinely good: accessibility

This is not a project that ignored quality — it front-loaded semantics over surface.

- `aria-label` on every landmark region (`Characters`, `Dice roller`, `Oracles`, `Journal`,
  `Campaign settings`, `Current stage: {name}`, `Result of {notation}`)
- `aria-busy="true"` on all four loading placeholders
- `role="alert"` on all eight error surfaces; `role="status"` on four non-error confirmations
- Correct `<label htmlFor>` on every input; `type="email"`, `required`, `minLength={8}`
- Semantic structure throughout: `<section>`, `<aside>`, `<header>`, `<dl>/<dt>/<dd>`, `<ol>` for
  the chronological journal, `<details>/<summary>` for the danger zone
- The E2E suite selects entirely by role and label — with zero classNames there is nothing else
  to select by, which has kept the semantics honest

**Maturity: visual ≈ 0/10, accessibility ≈ 6/10.** That is an unusual and, for once, a *lucky*
starting position: adding a visual layer to correct semantics is far easier than retrofitting
semantics onto a pretty div soup. The gaps that remain are the drawers not being real dialogs
(no `role="dialog"`, `aria-modal`, focus trap or Escape), no skip link, and no `color-scheme`.

---

## 3.2 What this product's design actually has to solve

Generic "make it look nice" advice will produce a SaaS dashboard, which is the wrong artefact.
The design brief follows from what solo play *is*:

**1. It is a reading-and-writing tool, used for hours.** The primary content is prose the user
wrote and prose the app offered. Typography, measure and contrast matter more than layout
cleverness. This is closer to a journalling app than to an admin panel.

**2. Sessions are long and often nocturnal.** Solo RPG play happens in the evening, frequently in
a dark room. Dark mode is not a nice-to-have here; it is the primary use case.

**3. The user is doing two jobs at once.** They are both player and GM. The interface has to make
"what am I supposed to be doing right now" answerable in one glance — the stage guidance is the
most important text on the screen and currently has the same visual weight as everything else.

**4. Oracles and dice are interruptions, not destinations.** You consult mid-thought and return.
They should be genuine overlays that open fast, take focus, close on Escape, and never lose the
journal draft behind them. Today they push the page down.

**5. Randomness needs ceremony.** A dice roll and an oracle draw are the moments the app *decides*
something. Right now they appear as unstyled numbers in a list. A little theatre — a reveal, a
distinct treatment for the drawn result — is the difference between "a tool printed a number" and
"the oracle answered".

**6. The journal is the artefact the user keeps.** It should read like a record worth re-reading,
grouped by stage, scannable by session. It is also the thing they will eventually want to export.

**7. Refusals are a core feature, not an error state.** "Cannot advance from Scene to Nowhere:
legal next stages are Sequel" is the app doing its job. It should be styled as guidance, not as a
red failure.

The mood to aim for: **a GM's notebook**, not a dashboard. Warm neutral paper in light mode, deep
ink in dark mode, one restrained accent for the app's own voice (guidance, oracle results),
generous line height, a real reading measure (~65ch for prose, not the current 640px for
everything).

---

## 3.3 A phased plan

Deliberately sequenced so it never becomes a rewrite, and so accessibility cannot regress.

### Phase 0 — Stop the bleeding *(minutes)*

Render `null` instead of "Dice roller closed." / "Oracles drawer closed.". Add a `viewport`
export and a favicon. These are shipping defects, not design.

### Phase 1 — Foundations *(no visual redesign yet)*

- `src/app/globals.css` with a `:root` token layer: colour (background, surface, foreground,
  muted, border, accent, danger, success), a type scale, a spacing scale, radii, shadows.
- Dark mode as a token swap: `:root` for light, `@media (prefers-color-scheme: dark)` guarded as
  `:root:not([data-theme="light"])`, and `:root[data-theme="dark"]` so an explicit toggle wins in
  both directions. Set `color-scheme` on `<html>`.
- `next/font` for one text face and one display face; delete the seven `fontFamily: 'system-ui'`
  repetitions.
- Decide the styling mechanism. **Recommendation: plain CSS with custom properties plus CSS
  Modules per component.** Four runtime dependencies today is a genuine asset; Tailwind would
  add a build step and a class-soup that fights the current zero-className cleanliness, and the
  app is 12 files. If the team prefers utility classes, Tailwind is defensible — but decide once
  and write it down.

### Phase 2 — Primitives

`src/components/ui/`: `Button` (primary/secondary/danger/ghost), `Input`, `Textarea`, `Card`,
`Drawer`, `Badge`, `Banner` (info/refusal/danger). Replace the five copy-pasted card idioms and
every raw `<button>`.

`Drawer` must be a real dialog: `role="dialog"`, `aria-modal="true"`, focus trap, Escape to close,
focus restored to the trigger — and actually overlay rather than stack. This closes the one real
a11y gap while delivering the biggest UX win.

### Phase 3 — Screen design

- **GM console.** Stage guidance as the focal element — largest type on the page, accent-marked.
  Suggested actions immediately beneath it as real buttons. Journal as the vertical spine with
  clear stage grouping. Oracles/dice as overlays.
- **Refusal banner** styled as guidance-with-alternatives, not as an error.
- **Campaign list** as readable cards showing system, stage and last-played.
- **Sign-in** as a real front door, not a bare form.
- Responsive at last: one breakpoint (~768px) is enough — single column on phones, the current
  centred measure above it.

### Phase 4 — Lock it in

- Playwright screenshot baselines for the four main screens, in **both** themes.
- An `axe-core` assertion in the E2E suite so the existing semantics cannot be lost to a restyle.
- Add `eslint-plugin-jsx-a11y` to the flat config.

**Do all of this behind a spec.** Per [02-specs.md](02-specs.md) R2, this is a real feature with
user-facing behaviour: open `specs/002-design-system/` rather than doing it as unlogged
maintenance. The last increment done that way shipped three critical regressions.

---

## 3.4 How to use AI tooling for the design work

**Design before code — use `/design`.** The `design` skill produces a multi-artboard canvas
(published as an Artifact with a visual editor) where screens can be laid out and refined by hand
before a line of CSS is written. This is the right first step: the failure mode with an AI and a
styling task is jumping straight to plausible-looking CSS with no unifying decision behind it.
Get the artboards agreed, *then* implement.

**Calibrate with `artifact-design`.** Load it before producing any HTML mockup; it exists to
decide how much design investment a given surface warrants and to keep tokens coherent across
light and dark.

**Screenshot before and after.** Playwright is already installed and configured. Capture the
current state (this audit's screenshots came from exactly that) so "before" is a fact rather than
a memory, and diff every phase against it.

**Use `claude-in-chrome` for interactive review** — hover, focus and keyboard behaviour are hard
to judge from a static screenshot and are where the drawer work will actually be validated.

**`dataviz`** is not needed now, but note it exists for the moment someone wants a session-stats
or oracle-distribution view — do not hand-roll chart colours then.

**Two constraints worth knowing before you publish mockups as Artifacts:** external resources are
CSP-restricted to a small CDN allowlist, and pages must be theme-aware (tokens on bare `:root`,
overridden under both `prefers-color-scheme` and `[data-theme]`) or they will look broken to half
the viewers. Building the mockups under those rules is also good practice for the real app.

---

## 3.5 Prompts

Extracted to [`docs/prompts/`](../prompts/README.md) as standalone briefs — each is runnable in a
fresh Claude Code session with no prior context, via its slash command or by pasting the file.
Run them in order; each depends on the one before.

| Prompt | Covers |
|---|---|
| [`/design-canvas`](../prompts/17-design-canvas.md) | The canvas and the token set — **start here**, before any code |
| [`/design-tokens`](../prompts/18-design-tokens.md) | Phase 1 — globals.css, tokens, dark mode, `next/font`, and the C4 fix |
| [`/ui-primitives`](../prompts/19-ui-primitives.md) | Phase 2 — `components/ui/`, and the drawers become real dialogs |
| [`/visual-regression`](../prompts/20-visual-regression.md) | Phase 4 — screenshot baselines and `axe` assertions in both themes |

Phase 3 (screen redesign) deliberately has no prompt yet: it should be specified as
`specs/002-design-system/` once the canvas exists, rather than executed from a brief.
