import { defineConfig, devices } from '@playwright/test';

/**
 * E2E smoke (T101): drives the quickstart happy path against the full
 * compose stack. The backend must already be up (docker compose up -d) —
 * only the Next.js dev server is (re)started here.
 *
 * Two projects. `chromium` is everything behavioural, and runs wherever it is
 * invoked. `visual` is the screenshot baselines, and is meaningful only in the
 * pinned container `scripts/visual-e2e.sh` starts: font rasterisation differs
 * between distros, so a baseline taken on a developer's machine and compared
 * on a CI runner disagrees by a hairline of antialiasing on every glyph. See
 * `docs/testing-visual-regression.md`.
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:3000';

const VISUAL_SPECS = /visual\.spec\.ts/;

export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 60_000,
    expect: {
        timeout: 10_000,
        toHaveScreenshot: {
            // Antialiasing moves a handful of pixels along every glyph edge
            // even inside one image; a hard zero would be flaky in the pinned
            // container too. 0.2% of the frame is well under any real change:
            // a lost token or a shifted block moves whole regions.
            maxDiffPixelRatio: 0.002,
        },
    },
    // One image per screen per scheme, named by the platform that rendered it,
    // so a stray baseline from an unpinned run is visible in the diff instead
    // of silently overwriting the one CI compares against.
    snapshotPathTemplate: '{testDir}/__screenshots__/{projectName}/{platform}/{arg}{ext}',
    fullyParallel: false,
    retries: 0,
    reporter: 'list',
    outputDir: 'test-results',
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            testIgnore: VISUAL_SPECS,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'visual',
            testMatch: VISUAL_SPECS,
            use: {
                ...devices['Desktop Chrome'],
                // Fixed, so the baselines do not depend on whatever the
                // default happens to be in a future Playwright release.
                viewport: { width: 1280, height: 800 },
                deviceScaleFactor: 1,
            },
        },
    ],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
              command: 'npm run dev',
              url: baseURL,
              reuseExistingServer: true,
              timeout: 120_000,
          },
});
