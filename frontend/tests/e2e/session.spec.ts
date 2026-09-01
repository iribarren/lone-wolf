import { expect, test } from '@playwright/test';

/**
 * Session lifecycle (B4): a player can leave, and leaving actually ends the
 * session — the gate comes back and the campaign list is not reachable
 * without signing in again.
 */
test('signing out returns to the gate and locks the campaign list', async ({ page }) => {
    const email = `e2e-signout-${Date.now()}@example.test`;

    await page.goto('/campaigns');

    // The gate stands in front of the (play) group until a session exists.
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('e2e-passphrase-123');
    await page.getByRole('button', { name: 'Register', exact: true }).click();

    // Signed in: the app renders and the control is reachable.
    await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeVisible();
    const signOut = page.getByRole('button', { name: 'Sign out' });
    await expect(signOut).toBeVisible();

    await signOut.click();

    // Back to exactly what a first-time visitor sees — no expiry notice,
    // because this was a deliberate sign-out.
    await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeHidden();
    await expect(page.getByRole('main').getByRole('alert')).toBeHidden();

    // The session is really gone, not just hidden by client state.
    expect(await page.evaluate(() => window.localStorage.getItem('lone-wolf.token'))).toBeNull();

    // Navigating straight back in shows the gate, not cached data.
    await page.goto('/campaigns');
    await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeHidden();
});

/**
 * A token the server rejects must surface as one honest expiry message
 * rather than a screen of failed queries (B4).
 */
test('a rejected token drops the player back to the gate with an expiry notice', async ({ page }) => {
    const email = `e2e-expiry-${Date.now()}@example.test`;

    await page.goto('/campaigns');
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('e2e-passphrase-123');
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'My campaigns' })).toBeVisible();

    // Stand in for the one-hour TTL: a token the backend will not accept.
    await page.evaluate(() => {
        window.localStorage.setItem('lone-wolf.token', 'eyJhbGciOiJSUzI1NiJ9.expired.signature');
    });
    await page.reload();

    await expect(page.getByRole('main').getByRole('alert')).toHaveText(/session expired/i);
    await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
    expect(await page.evaluate(() => window.localStorage.getItem('lone-wolf.token'))).toBeNull();
});
