import { expect, test } from '@playwright/test';

import { expectNoSeriousAxeViolations } from './support/axe';
import {
    beginCampaign,
    chooseDemoSystem,
    diceDrawer,
    oracleDrawer,
    registerPlayer,
    writeJournalEntry,
} from './support/journey';

/**
 * Accessibility gate for the player app (audit `03-design.md` §3.3 Phase 4).
 *
 * The app's semantics — an `aria-label` on every region, `role="alert"` on the
 * error surfaces, `role="status"` on confirmations, a real label on every
 * input — were a convention held up by review and by an E2E suite that selects
 * by role. This makes them a gate: the same six screens as the visual suite,
 * in both colour schemes, scanned by axe-core.
 *
 * Both schemes matter here as much as they do for the screenshots — colour
 * contrast is impact `serious`, and it is the one rule whose answer genuinely
 * differs between the light and the dark token set.
 */
for (const scheme of ['light', 'dark'] as const) {
    test.describe(`${scheme} theme`, () => {
        test.use({ colorScheme: scheme });

        test('no screen has a serious or critical accessibility violation', async ({ page }) => {
            await page.goto('/campaigns');
            await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
            await expectNoSeriousAxeViolations(page, `sign-in (${scheme})`);

            await registerPlayer(page, `a11y-${scheme}`);
            await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeVisible();

            await chooseDemoSystem(page);
            await expectNoSeriousAxeViolations(page, `start-a-campaign (${scheme})`);

            await beginCampaign(page);
            await writeJournalEntry(page, 'A door that was locked this morning stands open.');
            await expectNoSeriousAxeViolations(page, `console (${scheme})`);

            await page.getByRole('button', { name: 'Oracles', exact: true }).click();
            await expect(oracleDrawer(page)).toBeVisible();
            await expect(page.getByTestId('oracles-list')).toBeVisible();
            await expectNoSeriousAxeViolations(page, `console with the oracle drawer open (${scheme})`);
            await page.keyboard.press('Escape');
            await expect(oracleDrawer(page)).toBeHidden();

            await page.getByRole('button', { name: 'Dice', exact: true }).click();
            await expect(diceDrawer(page)).toBeVisible();
            await expectNoSeriousAxeViolations(page, `console with the dice widget open (${scheme})`);
            await page.keyboard.press('Escape');
            await expect(diceDrawer(page)).toBeHidden();

            await page.goto('/campaigns');
            await expect(page.getByRole('link', { name: /Scene-Sequel Demo/ })).toBeVisible();
            await expectNoSeriousAxeViolations(page, `campaign list (${scheme})`);
        });
    });
}
