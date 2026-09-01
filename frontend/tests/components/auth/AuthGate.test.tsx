import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AuthGate from '@/components/auth/AuthGate';
import { ApiError } from '@/lib/api/client';
import { clearSession, expireSession, saveSession } from '@/lib/auth';

const json = vi.fn();

vi.mock('@/lib/hooks/useApiClient', () => ({
    useApiClient: () => ({ json }),
}));

/** Fills and submits the sign-in form the gate renders. */
function signIn(password: string): void {
    fireEvent.change(screen.getByLabelText('Email'), {
        target: { value: 'someone@example.test' },
    });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: password } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
}

beforeEach(() => {
    window.localStorage.clear();
    clearSession();
    json.mockReset();
});

afterEach(() => {
    window.localStorage.clear();
    clearSession();
});

describe('AuthGate', () => {
    it('renders the sign-in form to a first-time visitor', () => {
        render(
            <AuthGate>
                <p>campaign list</p>
            </AuthGate>,
        );

        expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
        expect(screen.queryByText('campaign list')).not.toBeInTheDocument();
    });

    it('passes through to the app when a session exists', () => {
        saveSession({ token: 'live-token', roles: ['ROLE_PLAYER'] });

        render(
            <AuthGate>
                <p>campaign list</p>
            </AuthGate>,
        );

        expect(screen.getByText('campaign list')).toBeInTheDocument();
    });

    it('renders the sign-in form, not the app, once a 401 has invalidated the session', async () => {
        saveSession({ token: 'stale-token', roles: [] });

        render(
            <AuthGate>
                <p>campaign list</p>
            </AuthGate>,
        );
        expect(screen.getByText('campaign list')).toBeInTheDocument();

        // What ApiClient does when any query comes back 401.
        act(() => {
            expireSession();
        });

        await waitFor(() => {
            expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
        });
        expect(screen.queryByText('campaign list')).not.toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent(/session expired/i);
    });

    it('shows a credentials error, not the expiry message, on a wrong password', async () => {
        json.mockRejectedValue(new ApiError(401, 'Unauthorized', 'Invalid email or password.'));

        render(
            <AuthGate>
                <p>campaign list</p>
            </AuthGate>,
        );
        signIn('wrong-passphrase');

        const alert = await screen.findByRole('alert');
        expect(alert).toHaveTextContent(/Invalid email or password/i);
        expect(alert).not.toHaveTextContent(/session expired/i);
        expect(screen.queryByText('campaign list')).not.toBeInTheDocument();
    });

    it('signs the user in and stores the returned session', async () => {
        json.mockResolvedValue({ token: 'fresh-token', roles: ['ROLE_PLAYER'] });

        render(
            <AuthGate>
                <p>campaign list</p>
            </AuthGate>,
        );
        signIn('right-passphrase');

        expect(await screen.findByText('campaign list')).toBeInTheDocument();
        expect(window.localStorage.getItem('lone-wolf.token')).toBe('fresh-token');
    });

    it('clears the expiry message once the user signs in again', async () => {
        saveSession({ token: 'stale-token', roles: [] });
        render(
            <AuthGate>
                <p>campaign list</p>
            </AuthGate>,
        );
        act(() => {
            expireSession();
        });
        await screen.findByRole('heading', { name: 'Sign in' });

        json.mockResolvedValue({ token: 'fresh-token', roles: [] });
        signIn('right-passphrase');

        expect(await screen.findByText('campaign list')).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});
