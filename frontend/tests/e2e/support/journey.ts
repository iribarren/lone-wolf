import { expect, type Locator, type Page } from '@playwright/test';

/**
 * The one walkthrough the visual and accessibility suites share.
 *
 * Both suites need the same six screens in the same states, so the driving
 * lives here rather than being copied into each: a screen that changes shape
 * is then re-pointed once, and the two gates can never drift onto different
 * versions of the same page. Selection is by role and accessible name
 * throughout, following `play.spec.ts` — a query that survives a restyle but
 * not a lost label is exactly the assertion these suites exist to make.
 */

/** Seeded by `app:seed:demo`; the flow the quickstart walks. */
export const DEMO_SYSTEM = /Scene-Sequel Demo/;

const PASSWORD = 'e2e-passphrase-123';

/** A fresh account per test, so every campaign list holds exactly what we put in it. */
export function uniqueEmail(prefix: string): string {
    return `e2e-${prefix}-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.test`;
}

/** Registers a new player through the gate and leaves them on the campaign list. */
export async function registerPlayer(page: Page, prefix: string): Promise<void> {
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await page.getByLabel('Email').fill(uniqueEmail(prefix));
    await page.getByLabel('Password').fill(PASSWORD);
    await page.getByRole('button', { name: 'Register', exact: true }).click();
}

/**
 * Opens the creation screen with the demo system already chosen. Split from
 * the commit below so a caller can capture or scan the screen in the state a
 * player actually sees it — a system picked, the button live.
 */
export async function chooseDemoSystem(page: Page): Promise<void> {
    await page.goto('/campaigns/new');
    await expect(page.getByRole('heading', { name: 'Start a campaign' })).toBeVisible();
    await page.getByRole('radio', { name: DEMO_SYSTEM }).check();
}

/** Commits the binding and lands on the console. */
export async function beginCampaign(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Begin campaign' }).click();
    await expect(page.getByRole('heading', { name: 'Game master console' })).toBeVisible();
}

/** One journal entry, so the timeline is baselined with content rather than empty. */
export async function writeJournalEntry(page: Page, narrative: string): Promise<void> {
    await page.getByLabel(/Record what happened/).fill(narrative);
    await page.getByRole('button', { name: 'Add journal entry' }).click();
    await expect(page.getByText(narrative)).toBeVisible();
}

export function oracleDrawer(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Oracles' });
}

export function diceDrawer(page: Page): Locator {
    return page.getByRole('dialog', { name: 'Dice roller' });
}

/**
 * Everything on these screens that a second run would render differently:
 * the journal's locale-formatted stamps and the campaign list's "Updated …".
 * Unmasked, the suite fails for reasons no reviewer can act on, and a visual
 * suite that cries wolf is switched off within the week.
 */
export function volatileRegions(page: Page): Locator[] {
    return [
        page.locator('section[aria-label="Journal"] small'),
        page.locator('li small').filter({ hasText: /^Updated / }),
    ];
}
