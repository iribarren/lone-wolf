# Visual and accessibility regression

Two gates guard the design system and the semantics it carries: twelve
screenshot baselines and an `axe-core` scan, over the same six screens of the
player app, each in both colour schemes. They exist because until now nothing
did — a change that dropped an `aria-label`, or painted dark-mode text on a
light-mode ground, passed every check in the repository.

They are the Phase 4 of [`docs/audit/03-design.md`](audit/03-design.md) §3.3,
and they close [`docs/prompts/20-visual-regression.md`](prompts/20-visual-regression.md).

## What is covered

| Screen | Baseline | axe |
|---|---|---|
| Sign-in gate | `sign-in-{light,dark}.png` | ✓ |
| Start a campaign, one system chosen | `start-a-campaign-{light,dark}.png` | ✓ |
| Game master console, one journal entry | `console-{light,dark}.png` | ✓ |
| Console, oracle drawer open | `console-oracles-{light,dark}.png` | ✓ |
| Console, dice widget open | `console-dice-{light,dark}.png` | ✓ |
| Campaign list, one campaign | `campaign-list-{light,dark}.png` | ✓ |

Both themes are reached through Playwright's `colorScheme` context option,
which drives `prefers-color-scheme` — the app follows the system setting and
offers no toggle of its own. Dark mode is the primary use case (solo play
happens at night) and had no automated coverage of any kind before this.

Alongside them, `tests/e2e/drawer-dialogs.spec.ts` asserts the dialog behaviour
the primitives work built: Escape closes, Tab is trapped in both directions,
and focus returns to the control that opened the drawer. `Drawer.tsx` has unit
cover too, but jsdom has no layout — its own comment says so — and the trap is
only really answerable in a browser.

The suite is deliberately twelve images. Twelve get looked at when they change;
fifty get updated with `--update-snapshots` and never read again.

## Running them

    make up                                # the stack must already be up
    docker compose exec php bin/console app:seed:demo

    cd frontend
    npm run test:e2e                       # behavioural + a11y, then the baselines
    npm run test:e2e:visual                # just the baselines

`npm run test:e2e` runs the `chromium` project on your machine and then hands
the `visual` project to `scripts/visual-e2e.sh`. Everything needs the compose
stack running: these specs drive a browser, they do not boot an app.

## Where the baselines come from, and why it is a container

**Baselines are rendered inside `mcr.microsoft.com/playwright:v<version>-noble`,
by `frontend/scripts/visual-e2e.sh`. CI compares against them in the same
image; it never regenerates them.**

Playwright pins the browser, which is not enough. Rasterising text also depends
on the host's freetype, fontconfig and installed font packages, so the same
page on Ubuntu 26.04 and on a GitHub runner differs by a hairline of
antialiasing along every glyph — a difference no reviewer can act on and no
threshold cleanly separates from a real change. Cross-platform font rendering
is the usual reason a visual suite ends up deleted. One image renders them
everywhere instead.

The image tag follows `@playwright/test` in `frontend/package.json`; the script
derives it, so bumping the dependency and forgetting the image is not possible.
A Playwright upgrade *will* legitimately move the baselines — regenerate them
in the same commit as the bump, and say so in the message.

Two further things keep the images stable, both in
`tests/e2e/support/deterministic.ts`:

- **Ambient rows are filtered out.** Two screens list rows the test did not
  create — every active game system, and every oracle in scope. CI's own
  pipeline seeds a "Perf Sandbox" system through `app:seed:large-journal`, and
  anything authored through the backoffice lands in those lists too. The real
  requests are made and the real rows come back; everything `app:seed:demo`
  does not guarantee is dropped before the app sees it, so the ids stay real
  and starting a campaign from the pruned list is still a real write.
- **The Next.js dev overlay is hidden.** The E2E stack runs `next dev`, which
  paints a floating indicator over every page and grows a badge whenever it has
  something to say.

Timestamps and the campaign list's "Updated …" strings are masked
(`volatileRegions` in `tests/e2e/support/journey.ts`). The per-image tolerance
is `maxDiffPixelRatio: 0.002`; a shifted hue on one primary button moves 1–2%
of the frame, so the margin is roughly tenfold.

## Updating a baseline

    cd frontend
    npm run test:e2e:visual -- --update-snapshots

**An unexplained baseline update in a pull request is a review flag, not a
routine step.** The images are the record of what the design agreed to look
like; rewriting them is how a visual gate quietly stops being one. A PR that
touches `frontend/tests/e2e/__screenshots__/` must say, in its description,
which screens moved and why — and a reviewer who cannot match the new images to
an intended change should reject it and ask. Constitution VI: the reasoning is
part of the change set.

Baseline images live in their own commit, separate from the code that produced
them, so the diff of both stays readable.

If a comparison fails in CI, the `visual-diffs` artifact on the failed run
holds the `-expected`, `-actual` and `-diff` PNGs for every screen that moved.

## The accessibility floor

The gate fails on **serious** and **critical** violations. Minor and moderate
findings are largely advisory, and a gate that is red on day one is a gate that
gets commented out on day two.

As of this change the player app has **zero violations at every impact level**,
on all six screens in both themes — so a stricter setting would currently flag
nothing at all. Raising the floor is therefore free today; it is left where it
is so that the decision is made deliberately, with the survey re-run, rather
than inherited. Re-derive it without touching the gate:

    AXE_IMPACTS=minor,moderate,serious,critical npm run test:e2e

One thing the scan does *not* catch, worth knowing before trusting it too far:
a form control that loses its `<label>` but keeps a `placeholder` still has an
accessible name by the accname rules, so axe stays green while the field's name
silently degrades from "Dice notation" to "e.g. 1d20+5". Role-and-name queries
in the E2E specs are what actually holds that line, which is why every spec
here selects the way `play.spec.ts` does.

No rule is disabled anywhere in the suite. If one ever has to be, it belongs
next to the screen it is disabled for, with the reason written down.
