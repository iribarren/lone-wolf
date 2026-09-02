import { expect, test, type Locator, type Page } from '@playwright/test';

import { hideDevOverlay, pinListsToSeededContent } from './support/deterministic';
import {
    beginCampaign,
    chooseDemoSystem,
    diceDrawer,
    oracleDrawer,
    registerPlayer,
    volatileRegions,
    writeJournalEntry,
} from './support/journey';

/**
 * Visual baselines for the player app (audit `03-design.md` §3.3 Phase 4).
 *
 * Six screens in both colour schemes, twelve images. The pair exists because
 * dark mode is the app's primary use case — solo play happens at night — and
 * until now nothing exercised it at all: a token defined only under
 * `prefers-color-scheme: light` shipped green.
 *
 * These run in the `visual` project, which `frontend/scripts/visual-e2e.sh`
 * drives inside the pinned `mcr.microsoft.com/playwright` image. Font
 * rasterisation differs between a developer's distro and a CI runner, and
 * baselines that disagree by a hairline of antialiasing are the usual reason
 * visual suites end up deleted. See `docs/testing-visual-regression.md`.
 */

const NARRATIVE = 'The lantern gutters and the corridor answers with a sound like breathing.';

/**
 * Everything that has to be true of the page before the shutter opens: the
 * dev-server overlay gone (it is re-injected on every navigation, so this is
 * per-shot rather than once per test) and the web fonts finished loading — a
 * frame caught mid-swap is a flake, not a finding.
 */
async function settle(page: Page): Promise<void> {
    await hideDevOverlay(page);
    await page.evaluate(() => document.fonts.ready);
}

for (const scheme of ['light', 'dark'] as const) {
    test.describe(`${scheme} theme`, () => {
        // Drives `prefers-color-scheme`, which is how both themes are reached:
        // the app follows the system setting and offers no toggle of its own.
        test.use({ colorScheme: scheme });

        test('the player app matches its visual baseline', async ({ page }) => {
            await pinListsToSeededContent(page);

            const mask: Locator[] = volatileRegions(page);

            /** Whole-document shot: catches spacing and rhythm below the fold. */
            const fullPageShot = async (name: string): Promise<void> => {
                await settle(page);
                await expect
                    .soft(page)
                    .toHaveScreenshot(`${name}-${scheme}.png`, { fullPage: true, mask });
            };

            /**
             * Viewport shot, for the two drawer states: the drawers are
             * `position: fixed` over a scrim, so a full-page capture would
             * stretch the scrim down a page the drawer never covers and show
             * the overlay somewhere it never sits.
             */
            const overlayShot = async (name: string): Promise<void> => {
                await settle(page);
                await expect
                    .soft(page)
                    .toHaveScreenshot(`${name}-${scheme}.png`, { fullPage: false, mask });
            };

            // 1 — the gate every visitor meets first.
            await page.goto('/campaigns');
            await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
            await fullPageShot('sign-in');

            await registerPlayer(page, `visual-${scheme}`);
            await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeVisible();

            // 2 — starting a campaign, with a system chosen so the selected
            // state and the live primary button are both in the frame.
            await chooseDemoSystem(page);
            await fullPageShot('start-a-campaign');

            // 3 — the console, carrying a journal entry so the timeline is
            // baselined with content rather than in its empty state.
            await beginCampaign(page);
            await writeJournalEntry(page, NARRATIVE);
            await fullPageShot('console');

            // 4 — the oracle drawer as a modal over the console.
            await page.getByRole('button', { name: 'Oracles', exact: true }).click();
            await expect(oracleDrawer(page)).toBeVisible();
            await expect(page.getByTestId('oracles-list')).toBeVisible();
            await overlayShot('console-oracles');
            await page.keyboard.press('Escape');
            await expect(oracleDrawer(page)).toBeHidden();

            // 5 — the dice widget, unrolled: a result is random, and masking
            // the only thing the screen is about would baseline nothing.
            await page.getByRole('button', { name: 'Dice', exact: true }).click();
            await expect(diceDrawer(page)).toBeVisible();
            await overlayShot('console-dice');
            await page.keyboard.press('Escape');
            await expect(diceDrawer(page)).toBeHidden();

            // 6 — the list, now holding exactly the one campaign this test made.
            await page.goto('/campaigns');
            await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeVisible();
            await expect(page.getByRole('link', { name: /Scene-Sequel Demo/ })).toBeVisible();
            await fullPageShot('campaign-list');
        });
    });
}
