import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Banner from '@/components/ui/Banner';

describe('Banner', () => {
    it('keeps the live-region role the caller asks for', () => {
        render(
            <Banner variant="refusal" role="alert" data-testid="refusal-banner">
                Cannot advance from &ldquo;Scene&rdquo; to &ldquo;Nowhere&rdquo;.
            </Banner>,
        );

        const banner = screen.getByRole('alert');
        expect(banner).toHaveTextContent('Cannot advance');
        expect(screen.getByTestId('refusal-banner')).toBe(banner);
    });

    it('renders a refusal distinctly from a danger failure', () => {
        const { container: refusal } = render(<Banner variant="refusal">Legal next stages are Sequel.</Banner>);
        const refusalClass = refusal.firstElementChild?.className ?? '';

        const { container: danger } = render(<Banner variant="danger">Could not reach the server.</Banner>);
        const dangerClass = danger.firstElementChild?.className ?? '';

        expect(refusalClass).not.toBe('');
        expect(refusalClass).not.toEqual(dangerClass);
    });

    it('renders an info banner with no role when none is asked for', () => {
        render(<Banner variant="info">Nothing recorded yet.</Banner>);

        expect(screen.getByText('Nothing recorded yet.')).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
