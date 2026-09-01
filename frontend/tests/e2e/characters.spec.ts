import { expect, test } from '@playwright/test';

/**
 * Character round trip (B2, US5 — FR-021..FR-023): create a PC, see it on
 * the sheet, edit it, see the change. Runs against the seeded compose stack;
 * Scene-Sequel Demo ships one number field required of PCs only, and this
 * test never names it — every control it drives comes from the structure the
 * API returned, exactly as the app renders it.
 */
test('a player creates a character and edits it', async ({ page }) => {
    const email = `e2e-characters-${Date.now()}@example.test`;

    await page.goto('/');

    await page.getByRole('link', { name: 'Start a new campaign' }).click();
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('e2e-passphrase-123');
    await page.getByRole('button', { name: 'Register', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'Start a campaign' })).toBeVisible();
    await page.getByRole('radio', { name: /Scene-Sequel Demo/ }).check();
    await page.getByRole('button', { name: 'Begin campaign' }).click();

    await expect(page.getByRole('heading', { name: 'Game master console' })).toBeVisible();
    await expect(page.getByText('No characters yet.')).toBeVisible();

    await page.getByRole('button', { name: 'Add a character' }).click();

    const form = page.getByRole('form', { name: 'Add a character' });
    await form.getByLabel('Name', { exact: true }).fill('Vela');
    await form.getByLabel('Kind', { exact: true }).selectOption('pc');

    // A campaign with no characters carries no sheet shape yet, so the first
    // save is refused field by field — and that refusal names the field to
    // fill in, rather than leaving the player at a dead end.
    await form.getByRole('button', { name: 'Add character' }).click();
    const refusal = form.getByRole('alert').first();
    await expect(refusal).toContainText('required');

    const sheetField = form.locator('[data-testid^="character-field-"] input').first();
    await sheetField.fill('12');
    await form.getByRole('button', { name: 'Add character' }).click();

    // The sheet renders the saved character from the system's structure.
    const panel = page.getByRole('region', { name: 'Characters' });
    await expect(panel.getByRole('heading', { name: 'Vela' })).toBeVisible();
    await expect(panel).toContainText('Hit points');
    await expect(panel).toContainText('12');
    await expect(panel).toContainText('PC');

    // Editing reopens the same form, now with the real structure behind it.
    await panel.getByRole('button', { name: 'Edit Vela' }).click();

    const editor = page.getByRole('form', { name: 'Edit Vela' });
    await expect(editor.getByLabel('Hit points', { exact: false })).toHaveValue('12');
    await expect(editor.getByLabel('Kind', { exact: true })).toHaveCount(0);
    await editor.getByLabel('Name', { exact: true }).fill('Vela Ironhand');
    await editor.getByLabel('Hit points', { exact: false }).fill('9');
    await editor.getByRole('button', { name: 'Save character' }).click();

    await expect(panel.getByRole('heading', { name: 'Vela Ironhand' })).toBeVisible();
    await expect(panel).toContainText('9');
});
