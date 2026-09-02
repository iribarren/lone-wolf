'use client';

import type { InputHTMLAttributes } from 'react';

import styles from './Input.module.css';

export type InputProps = InputHTMLAttributes<HTMLInputElement>;

/**
 * A styled <input>, nothing more. Labels stay external <label htmlFor> in this
 * app, so every prop -- id, name, aria-describedby, aria-invalid -- is
 * forwarded untouched.
 */
export default function Input({ className, ...rest }: InputProps) {
    return <input className={[styles.field, className].filter(Boolean).join(' ')} {...rest} />;
}
