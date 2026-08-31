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

/**
 * Campaign-flow editor (A2, A3): the stage dropdowns must be usable on a cold
 * page load. `syncSelects(document.body)` never populated them — `document.body`
 * has no ancestor form — and once a blur did populate them, the collecting
 * selector was broad enough to offer the game system's own name as a stage.
 *
 * Nothing here may touch a field before asserting: the whole point is that an
 * admin should not have to blur a stage-name input to make the form work.
 */
const DEMO_SYSTEM = 'Scene-Sequel Demo';
const DEMO_STAGES = ['Setup', 'Scene', 'Sequel'];
const DEMO_STARTING_STAGE = 'Scene';
const DEMO_TRANSITIONS = [
    { from: 'Setup', to: 'Scene' },
    { from: 'Scene', to: 'Sequel' },
    { from: 'Sequel', to: 'Setup' },
];

async function openFlowEditor(page: import('@playwright/test').Page): Promise<void> {
    await page.goto(ADMIN_URL);
    await page.locator('input[name="_username"]').fill(ADMIN_EMAIL);
    await page.locator('input[name="_password"]').fill(ADMIN_PASSWORD);
    await page.locator('form').filter({ has: page.locator('input[name="_password"]') }).press('Enter');

    await page.getByRole('link', { name: 'Campaign flows' }).click();

    const row = page.locator('tr', { hasText: DEMO_SYSTEM }).first();
    const editUrl = await row
        .locator('a')
        .evaluateAll((links) => links.map((link) => (link as HTMLAnchorElement).href).find((href) => href.endsWith('/edit')));
    expect(editUrl, `no edit link for "${DEMO_SYSTEM}"`).toBeTruthy();

    await page.goto(editUrl as string);
    await expect(page.locator('select[name$="[starting_stage]"]')).toBeAttached();
}

/** The options and current value of a select, read without interacting with it. */
function readSelect(page: import('@playwright/test').Page, name: string) {
    return page.locator(`select[name="${name}"]`).evaluate((element) => {
        const select = element as HTMLSelectElement;

        return {
            options: Array.from(select.options).map((option) => option.value).filter((value) => value !== ''),
            value: select.value,
        };
    });
}

const FLOW = 'PersistenceGameSystem[flowDefinition]';

test('the flow editor offers every stage and pre-selects the stored ones on load', async ({ page }) => {
    await openFlowEditor(page);

    const startingStage = await readSelect(page, `${FLOW}[starting_stage]`);

    expect(startingStage.options).toEqual(DEMO_STAGES);
    expect(startingStage.value).toBe(DEMO_STARTING_STAGE);

    for (const [index, transition] of DEMO_TRANSITIONS.entries()) {
        const from = await readSelect(page, `${FLOW}[transitions][${index}][from]`);
        const to = await readSelect(page, `${FLOW}[transitions][${index}][to]`);

        expect(from.options, `transition ${index} "from" options`).toEqual(DEMO_STAGES);
        expect(to.options, `transition ${index} "to" options`).toEqual(DEMO_STAGES);
        expect(from.value, `transition ${index} "from" value`).toBe(transition.from);
        expect(to.value, `transition ${index} "to" value`).toBe(transition.to);
    }
});

/** Every value offered by any stage select on the page, deduplicated. */
function offeredStages(page: import('@playwright/test').Page): Promise<string[]> {
    return page
        .locator('select[name$="[starting_stage]"], select[name$="[from]"], select[name$="[to]"]')
        .evaluateAll((elements) => [
            ...new Set(
                elements.flatMap((element) =>
                    Array.from((element as HTMLSelectElement).options)
                        .map((option) => option.value)
                        .filter((value) => value !== ''),
                ),
            ),
        ]);
}

test('the flow editor never offers the game system itself as a stage', async ({ page }) => {
    await openFlowEditor(page);

    expect(await offeredStages(page)).toEqual(DEMO_STAGES);

    // A3 was only ever observable once a blur had populated the selects, so
    // assert the invariant holds through that path too — the same blur that
    // re-syncs the dropdowns must not smuggle `PersistenceGameSystem[name]` in.
    // Stage rows live in a collapsed EasyAdmin accordion; open the first one
    // so the blur is a real one rather than a synthetic event.
    await page.locator('.field-collection-item').first().locator('.accordion-button').click();
    await page.locator(`input[name="${FLOW}[stages][0][name]"]`).click();
    await page.locator(`input[name="${FLOW}[stages][0][name]"]`).press('Tab');

    expect(await offeredStages(page)).toEqual(DEMO_STAGES);
});
