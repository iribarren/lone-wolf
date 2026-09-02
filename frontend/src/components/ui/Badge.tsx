'use client';

import type { HTMLAttributes } from 'react';

import styles from './Badge.module.css';

export type BadgeVariant = 'neutral' | 'accent' | 'counsel';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
    variant?: BadgeVariant;
}

/**
 * A small status marker. `role` and `title` pass straight through: the drift
 * flag is a live region whose title lists what drifted, and losing either
 * would lose information the player relies on.
 */
export default function Badge({ variant = 'neutral', className, children, ...rest }: BadgeProps) {
    return (
        <span
            className={[styles.badge, styles[variant], className].filter(Boolean).join(' ')}
            {...rest}
        >
            {children}
        </span>
    );
}
