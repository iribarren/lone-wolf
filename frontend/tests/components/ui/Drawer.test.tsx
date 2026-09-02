import { fireEvent, render, screen } from '@testing-library/react';
import { useRef, useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import Drawer from '@/components/ui/Drawer';

/**
 * The one real accessibility gap in the app (audit §3.1): both drawers were
 * static <aside> elements in document flow with no dialog semantics. These
 * tests are the contract for the replacement.
 */
describe('Drawer', () => {
    it('exposes dialog semantics and its accessible name while open', () => {
        render(
            <Drawer open label="Oracles" onClose={() => {}} data-testid="oracles-drawer">
                <button type="button">Consult</button>
            </Drawer>,
        );

        const dialog = screen.getByRole('dialog', { name: 'Oracles' });
        expect(dialog).toBeInTheDocument();
        expect(dialog).toHaveAttribute('aria-modal', 'true');
        expect(screen.getByTestId('oracles-drawer')).toBe(dialog);
    });

    it('renders nothing at all while closed', () => {
        render(
            <Drawer open={false} label="Oracles" onClose={() => {}} data-testid="oracles-drawer">
                <button type="button">Consult</button>
            </Drawer>,
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(screen.queryByTestId('oracles-drawer')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Consult' })).not.toBeInTheDocument();
    });

    it('moves focus into the drawer when it opens', () => {
        render(
            <Drawer open label="Dice roller" onClose={() => {}}>
                <button type="button">Roll</button>
                <button type="button">Close</button>
            </Drawer>,
        );

        expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Roll' }));
    });

    it('closes on Escape', () => {
        const onClose = vi.fn();
        render(
            <Drawer open label="Dice roller" onClose={onClose}>
                <button type="button">Roll</button>
            </Drawer>,
        );

        fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('keeps Tab inside the drawer, wrapping in both directions', () => {
        render(
            <Drawer open label="Dice roller" onClose={() => {}}>
                <button type="button">First</button>
                <button type="button">Middle</button>
                <button type="button">Last</button>
            </Drawer>,
        );

        const first = screen.getByRole('button', { name: 'First' });
        const last = screen.getByRole('button', { name: 'Last' });
        const dialog = screen.getByRole('dialog');

        // Forward off the end wraps to the start.
        last.focus();
        fireEvent.keyDown(dialog, { key: 'Tab' });
        expect(document.activeElement).toBe(first);

        // Backward off the start wraps to the end.
        fireEvent.keyDown(dialog, { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(last);
    });

    it('returns focus to whatever opened it', () => {
        function Harness() {
            const [open, setOpen] = useState(false);
            const trigger = useRef<HTMLButtonElement>(null);

            return (
                <>
                    <button type="button" ref={trigger} onClick={() => setOpen(true)}>
                        Oracles
                    </button>
                    <Drawer open={open} label="Oracles" onClose={() => setOpen(false)}>
                        <button type="button" onClick={() => setOpen(false)}>
                            Close
                        </button>
                    </Drawer>
                </>
            );
        }

        render(<Harness />);
        const trigger = screen.getByRole('button', { name: 'Oracles' });

        trigger.focus();
        fireEvent.click(trigger);
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(document.activeElement).not.toBe(trigger);

        fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(document.activeElement).toBe(trigger);
    });

    it('does not throw when nothing focusable opened it', () => {
        const { rerender } = render(
            <Drawer open label="Oracles" onClose={() => {}}>
                <button type="button">Consult</button>
            </Drawer>,
        );

        expect(() =>
            rerender(
                <Drawer open={false} label="Oracles" onClose={() => {}}>
                    <button type="button">Consult</button>
                </Drawer>,
            ),
        ).not.toThrow();
    });
});
