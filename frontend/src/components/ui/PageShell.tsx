'use client';

import type { HTMLAttributes } from 'react';

import styles from './PageShell.module.css';

export type PageShellVariant = 'shell' | 'form' | 'bar';

export interface PageShellProps extends HTMLAttributes<HTMLElement> {
    /** `shell` is the page measure, `form` the narrow gate, `bar` the sign-out strip. */
    variant?: PageShellVariant;
    as?: 'main' | 'div';
}

export default function PageShell({
    variant = 'shell',
    as: Tag = 'main',
    className,
    children,
    ...rest
}: PageShellProps) {
    return (
        <Tag className={[styles[variant], className].filter(Boolean).join(' ')} {...rest}>
            {children}
        </Tag>
    );
}
