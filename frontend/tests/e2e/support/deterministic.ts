import type { Page } from '@playwright/test';

/**
 * Makes the baselined screens depend only on what `app:seed:demo` guarantees.
 *
 * Two of the six screens list rows the test did not create: the creation
 * screen lists every active game system, and the oracle drawer lists every
 * oracle in scope. Both are ambient — CI's own pipeline adds a "Perf Sandbox"
 * system through `app:seed:large-journal`, and any system or oracle authored
 * through the backoffice, by a person or by `admin.spec.ts`, lands in the same
 * lists. Baselined as-is, these two images encode the state of one database on
 * one day and go red on every machine that is not that one.
 *
 * So the responses are *filtered*, not fabricated: the real request is made,
 * the real rows come back, and everything the seeder does not promise is
 * dropped before the app sees it. The ids stay real, so creating a campaign
 * from the pruned list is still a real write against the real API.
 *
 * This is deliberately scoped to the visual suite. `a11y.spec.ts` and every
 * behavioural spec run against whatever is actually there.
 */

/** Authored by `SeedDemoContentCommand`; present in every seeded database. */
const SEEDED_SYSTEMS = ['Scene-Sequel Demo', 'Act Ladder', 'Freeform Sandbox'];

/** The only oracle the seeder scopes globally, so the only one every campaign sees. */
const SEEDED_ORACLES = ['Generic Weather'];

async function keepSeededRows(
    page: Page,
    url: string,
    named: (row: Record<string, unknown>) => unknown,
    keep: string[],
): Promise<void> {
    await page.route(url, async (route) => {
        const response = await route.fetch();
        const payload: unknown = await response.json();

        if (!Array.isArray(payload)) {
            // An error body or a Hydra envelope: pass it through untouched and
            // let the assertions in the spec say what went wrong.
            await route.fulfill({ response });

            return;
        }

        const rows = (payload as Record<string, unknown>[]).filter((row) => {
            const name = named(row);

            return typeof name === 'string' && keep.includes(name);
        });

        // Ordered by the seeder's list rather than the API's, so the rows sit
        // in the same order on every run.
        rows.sort((a, b) => keep.indexOf(String(named(a))) - keep.indexOf(String(named(b))));

        await route.fulfill({ response, json: rows });
    });
}

export async function pinListsToSeededContent(page: Page): Promise<void> {
    await keepSeededRows(page, '**/api/systems', (row) => row['name'], SEEDED_SYSTEMS);
    await keepSeededRows(page, '**/api/campaigns/*/oracles', (row) => row['title'], SEEDED_ORACLES);
}

/**
 * Hides the Next.js dev-server overlay button.
 *
 * The E2E stack runs `next dev`, which paints a floating indicator over the
 * bottom-left corner of every page. It is not part of the design, it changes
 * with the Next version, and it grows a badge whenever the dev server has
 * something to say — none of which belongs in a baseline.
 */
export async function hideDevOverlay(page: Page): Promise<void> {
    await page.addStyleTag({ content: 'nextjs-portal { display: none !important; }' });
}
