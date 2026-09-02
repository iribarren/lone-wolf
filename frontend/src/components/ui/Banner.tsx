'use client';

import type { HTMLAttributes } from 'react';

import styles from './Banner.module.css';

export type BannerVariant = 'info' | 'refusal' | 'danger';

export interface BannerProps extends HTMLAttributes<HTMLDivElement> {
    variant?: BannerVariant;
}

/*
 * A fork in the path: one branch open, one closed. Drawn rather than pulled
 * from an icon library -- the app has four runtime dependencies deliberately.
 */
function RefusalMark() {
    return (
        <svg
            width="20"
            height="20"
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M10 18.2v-6.1" />
            <path d="m10 12.1-5.1-5.1" strokeDasharray="2 2.4" opacity="0.5" />
            <path d="m10 12.1 5.1-5.1" />
            <circle cx="16.3" cy="5.7" r="1.9" fill="currentColor" stroke="none" />
            <path d="m2.3 2.6 3.2 3.2M5.5 2.6 2.3 5.8" opacity="0.5" />
        </svg>
    );
}

function AlertMark() {
    return (
        <svg
            width="20"
            height="20"
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            aria-hidden="true"
        >
            <circle cx="10" cy="10" r="7.4" />
            <path d="M10 6.2v4.6" />
            <circle cx="10" cy="13.9" r="0.95" fill="currentColor" stroke="none" />
        </svg>
    );
}

function InfoMark() {
    return (
        <svg
            width="20"
            height="20"
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            aria-hidden="true"
        >
            <circle cx="10" cy="10" r="7.4" />
            <path d="M10 9.2v4.6" />
            <circle cx="10" cy="6.1" r="0.95" fill="currentColor" stroke="none" />
        </svg>
    );
}

const MARKS: Record<BannerVariant, () => React.JSX.Element> = {
    info: InfoMark,
    refusal: RefusalMark,
    danger: AlertMark,
};

/**
 * `role` is deliberately not defaulted: the caller owns whether this is a live
 * region and which one. AdvanceActions' refusal keeps its role="alert".
 */
export default function Banner({ variant = 'info', className, children, ...rest }: BannerProps) {
    const Mark = MARKS[variant];

    return (
        <div
            className={[styles.banner, styles[variant], className].filter(Boolean).join(' ')}
            {...rest}
        >
            <span className={styles.icon}>
                <Mark />
            </span>
            <div className={styles.body}>{children}</div>
        </div>
    );
}
