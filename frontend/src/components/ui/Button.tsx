'use client';

import type { ButtonHTMLAttributes } from 'react';

import styles from './Button.module.css';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    /** While true the button is disabled and announces itself busy. */
    pending?: boolean;
    /** Label shown in place of the children while pending, e.g. "Rolling…". */
    pendingLabel?: string;
}

export default function Button({
    variant = 'secondary',
    pending = false,
    pendingLabel,
    type,
    disabled,
    className,
    children,
    ...rest
}: ButtonProps) {
    return (
        <button
            // Defaulting to "button" keeps a control inside a <form> from
            // submitting it by accident; callers opt into submit explicitly.
            type={type ?? 'button'}
            disabled={disabled || pending}
            aria-busy={pending || undefined}
            className={[styles.button, styles[variant], className].filter(Boolean).join(' ')}
            {...rest}
        >
            {pending && pendingLabel !== undefined ? pendingLabel : children}
        </button>
    );
}
