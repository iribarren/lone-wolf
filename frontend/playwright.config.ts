import { defineConfig, devices } from '@playwright/test';

/**
 * E2E smoke (T101): drives the quickstart happy path against the full
 * compose stack. The backend must already be up (docker compose up -d) —
 * only the Next.js dev server is (re)started here.
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:3000';

export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
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
            use: { ...devices['Desktop Chrome'] },
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
