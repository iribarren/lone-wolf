import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AuthGate from '@/components/auth/AuthGate';
import SignOutButton from '@/components/auth/SignOutButton';
import { clearSession, saveSession } from '@/lib/auth';

vi.mock('@/lib/hooks/useApiClient', () => ({
    useApiClient: () => ({ json: vi.fn() }),
}));

function renderWithClient(ui: React.ReactNode, queryClient: QueryClient) {
    return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
    window.localStorage.clear();
    clearSession();
});

afterEach(() => {
    window.localStorage.clear();
    clearSession();
});

describe('SignOutButton', () => {
    it('is a real button with an accessible name', () => {
        saveSession({ token: 'live-token', roles: [] });

        renderWithClient(<SignOutButton />, new QueryClient());

        const button = screen.getByRole('button', { name: /sign out/i });
        expect(button.tagName).toBe('BUTTON');
    });

    it('clears both storage keys', () => {
        saveSession({ token: 'live-token', roles: ['ROLE_PLAYER'] });

        renderWithClient(<SignOutButton />, new QueryClient());
        fireEvent.click(screen.getByRole('button', { name: /sign out/i }));

        expect(window.localStorage.getItem('lone-wolf.token')).toBeNull();
        expect(window.localStorage.getItem('lone-wolf.roles')).toBeNull();
    });

    it('empties the query cache so the next user sees no stale data', () => {
        saveSession({ token: 'live-token', roles: [] });
        const queryClient = new QueryClient();
        queryClient.setQueryData(['campaigns'], [{ id: 'c-1', gameSystemName: 'Previous user' }]);

        renderWithClient(<SignOutButton />, queryClient);
        fireEvent.click(screen.getByRole('button', { name: /sign out/i }));

        expect(queryClient.getQueryData(['campaigns'])).toBeUndefined();
        expect(queryClient.getQueryCache().getAll()).toHaveLength(0);
    });

    it('returns the user to the gate, without the expiry message', () => {
        saveSession({ token: 'live-token', roles: [] });

        renderWithClient(
            <AuthGate>
                <SignOutButton />
                <p>campaign list</p>
            </AuthGate>,
            new QueryClient(),
        );
        expect(screen.getByText('campaign list')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /sign out/i }));

        expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
        expect(screen.queryByText('campaign list')).not.toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});
