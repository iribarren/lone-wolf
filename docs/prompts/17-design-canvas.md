# 17 · Design the visual identity on a canvas

Wave 6 · no dependencies · branch — none, this produces no code · ~2 h

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. One person, playing alone, runs a campaign
through a graph of named stages: the app tells them what this part of play is for, they write a
journal entry, consult a weighted random table ("oracle"), roll dice, and advance. The frontend is
Next.js; the backend is Symfony and irrelevant to this prompt.

Read before starting:
- `docs/audit/03-design.md` — the full design analysis this prompt comes from, including the
  measured current state and the product brief
- `docs/functional-guide.md` §5 — what each screen actually does
</context>

<preconditions>
Boot the app and look at it before designing anything. Design proposals made without seeing the
artefact are guesses:

    docker compose up -d --build
    docker compose exec php bin/console app:seed:demo
    # then register at http://localhost:3000 and start a Scene-Sequel Demo campaign

Nothing else. This prompt produces a design canvas, not code.
</preconditions>

<problem>
**There is effectively no visual design.** Measured on 2026-08-30:

| Measure | Value |
|---|---|
| CSS files in the repo | 0 |
| Occurrences of `className` in `frontend/src` | 0 |
| Inline `style={{…}}` objects | 51 |
| Distinct colour values in the whole app | 6 (`#b00020`, `#ccc`, `#555`, `#eee`, `#ddd`, `#999`) |
| Design tokens | 0 |
| `@media` queries | 0 |
| Dark mode | none |
| `public/` directory | does not exist |
| Styled `<button>` elements | 0 — all raw browser defaults |

The whole design system is one copy-pasted idiom,
`{ border: '1px solid #ccc', borderRadius: 8, padding: '1rem' }`, duplicated across five files.

Accessibility, by contrast, is genuinely good — consistent `aria-label` on every region,
`role="alert"` on all eight error surfaces, `role="status"` on confirmations, `aria-busy` on
loading states, real `<label htmlFor>` on every input, and semantic landmarks throughout. The E2E
suite selects entirely by role and label because there is nothing else to select by, which has
kept it honest. **That is an asset to preserve, not a constraint to work around.**

The failure mode for this work is jumping straight to plausible-looking CSS with no unifying
decision behind it. Hence this prompt: decide the visual language first, on a canvas, before any
code is written.
</problem>

<instructions>
Invoke the `design` skill and produce a multi-artboard canvas with the brief below.

Artboards:
1. Sign-in / register
2. Campaign list — three campaigns, plus the empty state
3. Start a campaign — the system picker, with Scene-Sequel Demo, Act Ladder, Freeform Sandbox
4. Game master console — **light**
5. Game master console — **dark**
6. Oracle drawer open, showing a drawn result
7. Dice widget open, showing `2d6+3` → `[4, 2]` +3 = 9
8. The refusal banner

Product brief — design for *this*, not for a generic dashboard:

- One person, playing alone, for hours, usually at night. **Dark mode is the primary case**, not
  an afterthought.
- The screen is mostly prose: guidance the app offers, and journal entries the user writes.
  Reading comfort beats layout cleverness. A real reading measure for prose, not the current
  fixed 640px for everything.
- The user is player and GM at once. *"What am I supposed to do right now"* must be answerable at
  a glance — the current stage's guidance is the most important text on the page and today carries
  the same visual weight as everything else.
- Oracles and dice are interruptions you return from: real overlays, not page-flow sections.
- A dice roll or an oracle draw is the moment the app **decides** something. Give it a little
  ceremony — the difference between "a tool printed a number" and "the oracle answered".
- The journal is the artefact the user keeps. It should read like a record worth re-reading,
  grouped by stage, scannable by session.
- **Refusals are the product working, not errors.** "Cannot advance from Scene to Nowhere: legal
  next stages are Sequel" is the app doing its job. Style it as guidance-with-alternatives, not as
  a red failure.
- Mood: a game master's notebook, not a SaaS dashboard. One restrained accent for the app's own
  voice. Generous line height. Low chrome.

Deliver a coherent token set — colour, type scale, spacing, radii — that works in both themes,
rather than one-off styling per artboard. Prompt `18-design-tokens.md` implements that token set
directly, so it is the real output of this exercise.

Real copy, to use verbatim:

    Stage "Scene"    — "Open your Scene: pursue your intent until it resolves or twists."
    Stage "Sequel"   — "Run your Sequel: react, recover, and steer toward the next Scene."
    Action button    — "Advance to Sequel"
    Composer label   — Record what happened at "Scene"
    Oracle result    — "Sudden rain hammers the road ahead."
    Empty journal    — "Nothing recorded yet — your story starts below."
    Refusal          — Cannot advance from "Scene" to "Nowhere": legal next stages are "Sequel".
</instructions>

<constraints>
- The design must preserve the existing semantic structure — every region, label and live-region
  role. It is being restyled, not rebuilt.
- No icon-library dependency. Inline SVG only, if icons are used at all.
- No new runtime dependencies implied. The app has four, and that is deliberate.
- Do not write application code in this prompt. The canvas is the deliverable; implementation is
  prompts 18–20.
</constraints>

<acceptance_criteria>
- A published canvas with all eight artboards.
- A named token set — colour, type scale, spacing, radii — that both the light and dark artboards
  are visibly derived from, not two unrelated palettes.
- Every artboard uses the real copy above.
- The stage guidance is unambiguously the focal element of the console artboards.
- The refusal artboard does not read as an error state.
- Someone handed only the canvas could implement prompt 18 without asking what a colour should be.
</acceptance_criteria>

<completion>
No branch and no commits — this prompt produces a design canvas, not code.

Report honestly: the canvas URL, the token set as named values, the one aesthetic decision you
would defend hardest, and anything in the product brief you deliberately did not follow, with your
reasoning. If you could not make part of the brief work visually, say so plainly rather than
quietly dropping it or weakening it — an unresolved tension named here is worth more than a canvas
that looks finished and is not.
</completion>
