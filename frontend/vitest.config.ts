import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: './tests/setup.ts',
        // tests/e2e is Playwright territory (T101) — driven by `npm run test:e2e`.
        exclude: ['**/node_modules/**', 'tests/e2e/**'],
        // The default 5s is measured against a warm Vite transform cache. In a
        // cold container `make test` spent 162s transforming and the first test
        // in two files timed out while the rest of the suite passed. This buys
        // headroom for a cold cache; it relaxes no assertion.
        testTimeout: 20_000,
    },
});
