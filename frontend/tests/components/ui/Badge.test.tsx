import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Badge from '@/components/ui/Badge';

describe('Badge', () => {
    it('forwards the live-region role and the title the caller sets', () => {
        render(
            <Badge role="status" title="hp is no longer a number" data-testid="drift-flag">
                ⚑ flagged for review
            </Badge>,
        );

        const badge = screen.getByRole('status');
        expect(badge).toHaveAttribute('title', 'hp is no longer a number');
        expect(screen.getByTestId('drift-flag')).toBe(badge);
        expect(badge).toHaveTextContent('flagged for review');
    });

    it('renders without a role when none is given', () => {
        render(<Badge>global</Badge>);

        expect(screen.getByText('global')).toBeInTheDocument();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
