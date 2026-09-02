'use client';

import { useCallback, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

import styles from './Drawer.module.css';

export interface DrawerProps {
    open: boolean;
    /** Becomes the dialog's accessible name, e.g. "Oracles". */
    label: string;
    onClose: () => void;
    children: ReactNode;
    'data-testid'?: string;
}

/*
 * Anything that can hold focus. `:not([tabindex='-1'])` keeps the dialog
 * element itself -- our fallback focus target -- out of the tab order.
 */
const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

export default function Drawer({ open, label, onClose, children, ...rest }: DrawerProps) {
    const dialog = useRef<HTMLDivElement>(null);
    const opener = useRef<HTMLElement | null>(null);

    const focusable = useCallback((): HTMLElement[] => {
        const root = dialog.current;
        if (!root) {
            return [];
        }

        // No visibility filtering: the drawers render their controls
        // conditionally rather than hiding them with CSS, and offsetParent is
        // always null under jsdom, so a layout-based check would be a lie in
        // tests and a no-op in the browser.
        return Array.from(root.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
            (element) => !element.hasAttribute('hidden'),
        );
    }, []);

    // Remember who opened us, move focus in, and hand it back on close. The
    // opener may be gone by then (unmounted, or never focusable), so restoring
    // is conditional -- never throw on the way out.
    useEffect(() => {
        if (!open) {
            return;
        }

        const previous = document.activeElement;
        opener.current = previous instanceof HTMLElement ? previous : null;

        const first = focusable()[0];
        (first ?? dialog.current)?.focus();

        return () => {
            const target = opener.current;
            opener.current = null;
            if (target && target.isConnected) {
                target.focus();
            }
        };
    }, [open, focusable]);

    if (!open) {
        return null;
    }

    function onKeyDown(event: React.KeyboardEvent<HTMLDivElement>): void {
        if (event.key === 'Escape') {
            event.stopPropagation();
            onClose();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        // The trap: Tab off either end wraps to the other, so focus never
        // reaches the page behind an aria-modal dialog.
        const elements = focusable();
        if (elements.length === 0) {
            event.preventDefault();

            return;
        }

        const first = elements[0];
        const last = elements[elements.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && (active === first || active === dialog.current)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || active === dialog.current)) {
            event.preventDefault();
            first.focus();
        }
    }

    return (
        <>
            <div className={styles.scrim} onClick={onClose} aria-hidden="true" />
            <div
                ref={dialog}
                role="dialog"
                aria-modal="true"
                aria-label={label}
                tabIndex={-1}
                className={styles.drawer}
                onKeyDown={onKeyDown}
                {...rest}
            >
                {children}
            </div>
        </>
    );
}
