'use client';

import type { HTMLAttributes } from 'react';

import styles from './Card.module.css';

/**
 * The card idiom that was copy-pasted across five files. Polymorphic because
 * the app needs it as a landmark <section>, a list <li> and an <aside>, and
 * flattening those to <div> would cost the semantics the E2E suite selects by.
 */
export type CardElement = 'div' | 'section' | 'li' | 'aside' | 'article';

export interface CardProps extends HTMLAttributes<HTMLElement> {
    as?: CardElement;
}

export default function Card({ as: Tag = 'div', className, children, ...rest }: CardProps) {
    return (
        <Tag
            className={[styles.card, Tag === 'li' ? styles.plain : null, className]
                .filter(Boolean)
                .join(' ')}
            {...rest}
        >
            {children}
        </Tag>
    );
}
