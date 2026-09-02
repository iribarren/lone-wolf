import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Card from '@/components/ui/Card';

describe('Card', () => {
    it('renders a div by default', () => {
        const { container } = render(<Card>Body</Card>);

        expect(container.firstElementChild?.tagName).toBe('DIV');
    });

    it('renders as the element asked for, keeping its landmark label', () => {
        render(
            <Card as="section" aria-label="Current stage: Scene">
                Guidance
            </Card>,
        );

        expect(screen.getByRole('region', { name: 'Current stage: Scene' })).toBeInTheDocument();
    });

    it('can render as a list item so a list of cards stays a list', () => {
        const { container } = render(
            <ul>
                <Card as="li">A campaign</Card>
            </ul>,
        );

        expect(container.querySelector('li')).toHaveTextContent('A campaign');
    });
});
