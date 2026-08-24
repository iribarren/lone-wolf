import { expect, test } from '@playwright/test';

/**
 * Quickstart happy path (T101): login → new campaign → guidance → advance →
 * journal entry visible. Runs against the seeded compose stack; the
 * Scene-Sequel Demo system comes from `app:seed:demo`.
 */
test('guided solo play loop', async ({ page }) => {
    const email = `e2e-${Date.now()}@example.test`;

    await page.goto('/');

    // AuthGate intercepts the (play) group until a session exists.
    await page.getByRole('link', { name: 'Start a new campaign' }).click();
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('e2e-passphrase-123');
    await page.getByRole('button', { name: 'Register', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'Start a campaign' })).toBeVisible();

    // Pick the seeded demo system — the binding is permanent (FR-012).
    await page.getByRole('radio', { name: /Scene-Sequel Demo/ }).check();
    await page.getByRole('button', { name: 'Begin campaign' }).click();

    const console = page.getByRole('heading', { name: 'Game master console' });
    await expect(console).toBeVisible();

    // Landed on the designated starting stage with its opening guidance.
    const stagePanel = page.getByRole('region', { name: /Current stage:/ });
    await expect(stagePanel).toContainText('Scene');
    await expect(stagePanel).toContainText('Open your Scene');

    // Advance along the only legal transition (player-confirmed, FR-016).
    await page.getByRole('button', { name: /Sequel/i }).first().click();
    await expect(stagePanel).toContainText('Sequel');
    await expect(stagePanel).toContainText('Run your Sequel');

    // Journal entry keyed to the current stage becomes visible immediately.
    await page
        .getByLabel(/Record what happened/)
        .fill('The sequel turns on a betrayal nobody saw coming.');
    await page.getByRole('button', { name: 'Add journal entry' }).click();
    await expect(
        page.getByText('The sequel turns on a betrayal nobody saw coming.'),
    ).toBeVisible();
});
