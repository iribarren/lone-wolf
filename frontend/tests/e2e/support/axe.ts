import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';
import type { ImpactValue, Result } from 'axe-core';

/**
 * The severity floor this project gates on.
 *
 * Deliberately not "zero violations of any impact": `minor` and `moderate`
 * findings are largely advisory, and a gate that is red on day one is a gate
 * that gets commented out on day two. `serious` and `critical` are the ones
 * that actually stop someone using the app.
 *
 * `AXE_IMPACTS` lowers the floor for a survey without editing the gate — how
 * `docs/testing-visual-regression.md` re-derives what a stricter setting would
 * flag, rather than quoting a number someone once saw:
 *
 *     AXE_IMPACTS=minor,moderate,serious,critical npm run test:e2e
 */
const BLOCKING: ReadonlyArray<string> = (process.env.AXE_IMPACTS ?? 'serious,critical')
    .split(',')
    .map((impact) => impact.trim())
    .filter((impact) => impact !== '');

function blocks(impact: ImpactValue | undefined | null): boolean {
    return impact !== undefined && impact !== null && BLOCKING.includes(impact);
}

/** Turns axe's node reports into something a reviewer can act on from a CI log. */
function describe(violations: Result[]): string {
    return violations
        .map((violation) => {
            const where = violation.nodes.map((node) => `      - ${node.target.join(' ')}`).join('\n');

            return `  [${violation.impact}] ${violation.id}: ${violation.help}\n${where}`;
        })
        .join('\n');
}

/**
 * Scans the current page and fails on any violation at or above the floor.
 *
 * `screen` names the surface in the failure message; without it a CI log says
 * only that something, somewhere in a six-screen walkthrough, is inaccessible.
 * The assertion is soft so one bad screen does not hide the other five —
 * the run still fails, but it fails with the whole picture.
 */
export async function expectNoSeriousAxeViolations(page: Page, screen: string): Promise<void> {
    const { violations } = await new AxeBuilder({ page }).analyze();
    const blocking = violations.filter((violation) => blocks(violation.impact));

    expect
        .soft(
            blocking.map((violation) => violation.id),
            `${screen} — accessibility violations (${BLOCKING.join('/')}):\n${describe(blocking)}`,
        )
        .toEqual([]);
}
