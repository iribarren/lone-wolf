import { expect, test, type Locator, type Page } from '@playwright/test';

import { beginCampaign, chooseDemoSystem, diceDrawer, oracleDrawer, registerPlayer } from './support/journey';

/**
 * Dialog behaviour for the two drawers, in the browser (audit `03-design.md`
 * §3.3 Phase 4, closing what prompt 19 built).
 *
 * `Drawer.tsx` has unit cover, but jsdom has no layout and no real focus ring:
 * `offsetParent` is always null there, so the trap's own comment notes it
 * cannot filter by visibility. Whether Escape, the Tab wrap and the focus
 * hand-back actually work is only answerable in a browser, which is where the
 * next restyle will break them.
 */

interface Drawer {
    name: string;
    trigger: string;
    locator: (page: Page) => Locator;
    /** Its own accessible name once open — the dock button relabels itself. */
    closeTrigger: string;
}

const DRAWERS: Drawer[] = [
    { name: 'Oracles', trigger: 'Oracles', locator: oracleDrawer, closeTrigger: 'Hide oracles' },
    { name: 'Dice roller', trigger: 'Dice', locator: diceDrawer, closeTrigger: 'Hide dice' },
];

/** A registered player sitting on a fresh console, which is where both drawers live. */
async function openConsole(page: Page, prefix: string): Promise<void> {
    await page.goto('/campaigns');
    await registerPlayer(page, prefix);
    await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeVisible();
    await chooseDemoSystem(page);
    await beginCampaign(page);
}

/** What the browser says currently holds focus, as a locator. */
function focused(page: Page): Locator {
    return page.locator('*:focus');
}

for (const drawer of DRAWERS) {
    test.describe(`the ${drawer.name} drawer behaves as a modal dialog`, () => {
        test('Escape closes it and focus returns to the control that opened it', async ({ page }) => {
            await openConsole(page, `dialog-esc-${drawer.name.toLowerCase().replace(/\W+/g, '-')}`);

            const opener = page.getByRole('button', { name: drawer.trigger, exact: true });
            await opener.click();

            const dialog = drawer.locator(page);
            await expect(dialog).toBeVisible();
            await expect(dialog).toHaveAttribute('aria-modal', 'true');

            // Focus moved into the dialog rather than staying on the page behind it.
            await expect(dialog.locator('*:focus')).toHaveCount(1);

            await page.keyboard.press('Escape');
            await expect(dialog).toBeHidden();

            // The opener has relabelled itself back, and holds focus again —
            // a keyboard user is returned to where they were, not to the top
            // of the document.
            await expect(focused(page)).toHaveAccessibleName(drawer.trigger);
        });

        test('Tab is trapped inside it, wrapping at both ends', async ({ page }) => {
            await openConsole(page, `dialog-tab-${drawer.name.toLowerCase().replace(/\W+/g, '-')}`);

            await page.getByRole('button', { name: drawer.trigger, exact: true }).click();
            const dialog = drawer.locator(page);
            await expect(dialog).toBeVisible();

            // Walk forwards well past the number of controls either drawer
            // holds. If the trap leaks, focus lands on the console behind and
            // the dialog stops owning it.
            for (let step = 0; step < 12; step += 1) {
                await page.keyboard.press('Tab');
                await expect(
                    dialog.locator('*:focus'),
                    `Tab ${step + 1} escaped the ${drawer.name} dialog`,
                ).toHaveCount(1);
            }

            // And backwards, which is the direction the wrap is easiest to get wrong.
            for (let step = 0; step < 12; step += 1) {
                await page.keyboard.press('Shift+Tab');
                await expect(
                    dialog.locator('*:focus'),
                    `Shift+Tab ${step + 1} escaped the ${drawer.name} dialog`,
                ).toHaveCount(1);
            }
        });

        test('its own Close control closes it too', async ({ page }) => {
            await openConsole(page, `dialog-close-${drawer.name.toLowerCase().replace(/\W+/g, '-')}`);

            await page.getByRole('button', { name: drawer.trigger, exact: true }).click();
            const dialog = drawer.locator(page);
            await expect(dialog).toBeVisible();

            await dialog.getByRole('button', { name: 'Close' }).click();
            await expect(dialog).toBeHidden();
            await expect(page.getByRole('button', { name: drawer.closeTrigger, exact: true })).toHaveCount(0);
        });
    });
}
