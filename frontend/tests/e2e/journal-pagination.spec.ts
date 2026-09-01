import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

/**
 * Journal pagination (B3 — FR-017): the reader can walk the whole history,
 * not just the 50 newest entries.
 *
 * The first case runs against the SC-008 performance fixture and asserts the
 * entry count after every click, so the fixture must be freshly seeded — the
 * seeder replaces that campaign's entries, and `check-journal-performance.sh`
 * runs it too:
 *
 *     docker compose exec php bin/console app:seed:large-journal \
 *       --email=perf@example.com --password=perf-player-password --entries=500
 *
 * The second seeds its own two-stage campaign over the API, because the perf
 * fixture writes every entry on one stage and cannot show a group boundary.
 */
const API_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';
const PERF_EMAIL = process.env.PERF_EMAIL ?? 'perf@example.com';
const PERF_PASSWORD = process.env.PERF_PASSWORD ?? 'perf-player-password';
const PERF_ENTRIES = Number(process.env.PERF_ENTRIES ?? 500);

const PAGE_SIZE = 50;

/** Signs a session into localStorage the way AuthGate stores it. */
async function useSession(page: Page, token: string): Promise<void> {
    await page.addInitScript((jwt: string) => {
        window.localStorage.setItem('lone-wolf.token', jwt);
        window.localStorage.setItem('lone-wolf.roles', JSON.stringify(['ROLE_USER']));
    }, token);
}

async function post(request: APIRequestContext, path: string, body: unknown, token?: string): Promise<unknown> {
    const response = await request.post(`${API_BASE}${path}`, {
        data: body as Record<string, unknown>,
        headers: {
            Accept: 'application/json',
            ...(token === undefined ? {} : { Authorization: `Bearer ${token}` }),
        },
    });
    expect(response.ok(), `${path} → ${response.status()}`).toBeTruthy();

    return await response.json();
}

test('the reader pages back to the beginning of a long journal', async ({ page, request }) => {
    // 500 entries is ten pages of clicking; the stack answers each in
    // milliseconds, but the render grows to 500 list items.
    test.setTimeout(180_000);

    const session = (await post(request, '/api/auth/login', {
        email: PERF_EMAIL,
        password: PERF_PASSWORD,
    })) as { token: string };
    await useSession(page, session.token);

    await page.goto('/campaigns');
    await page.getByRole('link', { name: /Perf Sandbox/ }).click();

    const journal = page.getByRole('region', { name: 'Journal' });
    const entries = journal.getByRole('listitem');
    const loadMore = journal.getByRole('button', { name: 'Load earlier entries' });

    // The newest entry is on screen and older history is advertised.
    await expect(journal.getByText(`[perf fixture ${String(PERF_ENTRIES).padStart(5, '0')}]`, { exact: false })).toBeVisible();
    await expect(entries).toHaveCount(PAGE_SIZE);
    await expect(loadMore).toBeVisible();

    // Walk the whole history. The exact count after every click also proves no
    // entry is duplicated or dropped at a page seam.
    for (let loaded = PAGE_SIZE; loaded < PERF_ENTRIES; loaded += PAGE_SIZE) {
        await loadMore.click();
        await expect(entries).toHaveCount(Math.min(loaded + PAGE_SIZE, PERF_ENTRIES));
    }

    await expect(journal.getByText('[perf fixture 00001]', { exact: false })).toBeVisible();

    // At the beginning of history the control is gone, not merely disabled.
    await expect(loadMore).toHaveCount(0);
    await expect(journal.getByRole('button', { name: /earlier entries/i })).toHaveCount(0);
});

test('stage groups survive a page seam and a new entry keeps the loaded pages', async ({ page, request }) => {
    test.setTimeout(120_000);

    const email = `e2e-journal-${Date.now()}@example.test`;
    const session = (await post(request, '/api/auth/register', {
        email,
        password: 'e2e-passphrase-123',
    })) as { token: string };
    const token = session.token;

    const systemsResponse = await request.get(`${API_BASE}/api/systems`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
    const systems = (await systemsResponse.json()) as { systemId: string; name: string }[];
    const demo = systems.find((system) => system.name.includes('Scene-Sequel Demo'));
    expect(demo, 'app:seed:demo must have run').toBeDefined();

    const campaign = (await post(request, '/api/campaigns', { gameSystemId: demo?.systemId }, token)) as {
        id: string;
    };

    // 30 entries on Scene, then 30 on Sequel: the 50-entry page boundary falls
    // inside the Scene run, so page two must join a group page one opened.
    for (let i = 1; i <= 30; i += 1) {
        await post(request, `/api/campaigns/${campaign.id}/journal`, { narrative: `Scene note ${String(i).padStart(2, '0')}` }, token);
    }
    await post(request, `/api/campaigns/${campaign.id}/advance`, { toStageId: 'Sequel' }, token);
    for (let i = 1; i <= 30; i += 1) {
        await post(request, `/api/campaigns/${campaign.id}/journal`, { narrative: `Sequel note ${String(i).padStart(2, '0')}` }, token);
    }

    await useSession(page, token);
    await page.goto(`/campaigns/${campaign.id}`);

    const journal = page.getByRole('region', { name: 'Journal' });
    const entries = journal.getByRole('listitem');
    const loadMore = journal.getByRole('button', { name: 'Load earlier entries' });

    await expect(entries).toHaveCount(PAGE_SIZE);
    await expect(journal.getByRole('heading', { level: 3 })).toHaveText(['Sequel', 'Scene']);

    await loadMore.click();
    await expect(entries).toHaveCount(60);
    await expect(loadMore).toHaveCount(0);

    // The seam entry appears once, under the stage it was written on, and the
    // groups did not multiply as pages arrived.
    await expect(journal.getByText('Scene note 01', { exact: true })).toHaveCount(1);
    await expect(journal.getByRole('heading', { level: 3 })).toHaveText(['Sequel', 'Scene']);
    const scene = journal.locator('div', { has: page.getByRole('heading', { level: 3, name: 'Scene' }) }).last();
    await expect(scene.getByRole('listitem')).toHaveCount(30);

    // Writing with two pages loaded shows the entry immediately and keeps the
    // history the reader already paged back through.
    await page.getByLabel(/Record what happened/).fill('Written with the whole history open.');
    await page.getByRole('button', { name: 'Add journal entry' }).click();

    await expect(journal.getByText('Written with the whole history open.', { exact: true })).toBeVisible();
    await expect(entries).toHaveCount(61);
    await expect(journal.getByText('Scene note 01', { exact: true })).toHaveCount(1);
});
