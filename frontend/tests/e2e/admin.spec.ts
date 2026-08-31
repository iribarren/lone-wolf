import { expect, test } from '@playwright/test';

/**
 * Backoffice reachability (A1): the documented admin URL must survive the
 * published port. `public/admin/` used to shadow the `/admin` route, so nginx
 * answered with its own directory redirect rebuilt from the container's
 * listen port 80 — an admin could sign in and still never reach the dashboard.
 */
const ADMIN_URL = process.env.E2E_ADMIN_URL ?? 'http://localhost:8080/admin';
const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.test';
const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? 'admin-passphrase';

test('the documented admin URL reaches the dashboard on its published port', async ({ page }) => {
    const documented = new URL(ADMIN_URL);

    await page.goto(ADMIN_URL);

    // The route may legitimately bounce to the login form, but never off-host.
    expect(new URL(page.url()).host).toBe(documented.host);

    await page.locator('input[name="_username"]').fill(ADMIN_EMAIL);
    await page.locator('input[name="_password"]').fill(ADMIN_PASSWORD);
    await page.locator('form').filter({ has: page.locator('input[name="_password"]') }).press('Enter');

    await expect(page.getByRole('link', { name: 'Game systems' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Campaign flows' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Oracles' })).toBeVisible();

    // Signing in must land on the dashboard at the same host:port we opened.
    expect(new URL(page.url()).host).toBe(documented.host);
});
