'use client';

import type { TextareaHTMLAttributes } from 'react';

import styles from './Input.module.css';

export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

/** The prose sibling of Input: the journal and interpretations are read at length. */
export default function Textarea({ className, ...rest }: TextareaProps) {
    return (
        <textarea
            className={[styles.field, styles.prose, className].filter(Boolean).join(' ')}
            {...rest}
        />
    );
}
