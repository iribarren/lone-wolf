import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import Button from '@/components/ui/Button';

describe('Button', () => {
    it('renders a real button carrying its accessible name', () => {
        render(<Button>Advance to Sequel</Button>);

        expect(screen.getByRole('button', { name: 'Advance to Sequel' })).toBeInTheDocument();
    });

    it('defaults to type="button" so it never submits a form by accident', () => {
        render(<Button>Consult</Button>);

        expect(screen.getByRole('button', { name: 'Consult' })).toHaveAttribute('type', 'button');
    });

    it('honours an explicit submit type', () => {
        render(<Button type="submit">Roll</Button>);

        expect(screen.getByRole('button', { name: 'Roll' })).toHaveAttribute('type', 'submit');
    });

    it('swaps its label and disables itself while pending', () => {
        const onClick = vi.fn();
        render(
            <Button pending pendingLabel="Rolling…" onClick={onClick}>
                Roll
            </Button>,
        );

        const button = screen.getByRole('button', { name: 'Rolling…' });
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');

        fireEvent.click(button);
        expect(onClick).not.toHaveBeenCalled();
    });

    it('stays disabled when the caller disables it', () => {
        render(<Button disabled>Begin campaign</Button>);

        expect(screen.getByRole('button', { name: 'Begin campaign' })).toBeDisabled();
    });

    it('forwards the accessible name given as aria-label', () => {
        render(<Button aria-label="Add a character">+</Button>);

        expect(screen.getByRole('button', { name: 'Add a character' })).toBeInTheDocument();
    });
});
