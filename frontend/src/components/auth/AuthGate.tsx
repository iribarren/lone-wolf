'use client';

/**
 * Compact authentication gate for the player app (US2). Renders the
 * login/registration form until a session exists, then passes through.
 */
import { useState, useSyncExternalStore, type FormEvent, type ReactNode } from 'react';

import { ApiError, apiPath } from '@/lib/api/client';
import { useApiClient } from '@/lib/hooks/useApiClient';
import { loadSession, saveSession, sessionExpired, subscribeToSession } from '@/lib/auth';

const EXPIRED_MESSAGE = 'Your session expired. Sign in to continue.';

interface AuthResponse {
    token?: string;
    roles?: string[];
}

export default function AuthGate({ children }: { children: ReactNode }) {
    const api = useApiClient();
    const [mode, setMode] = useState<'login' | 'register'>('login');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState(false);

    // The store, not a one-shot read, decides: signing out or a 401 anywhere in
    // the app must bring the gate straight back. The server snapshot is
    // "signed out" because localStorage does not exist during SSR.
    const authenticated = useSyncExternalStore(
        subscribeToSession,
        () => loadSession() !== null,
        () => false,
    );
    const expired = useSyncExternalStore(subscribeToSession, sessionExpired, () => false);

    if (authenticated) {
        return <>{children}</>;
    }

    // A failed sign-in attempt is about the credentials just typed, so it wins
    // over the standing expiry notice.
    const notice = error ?? (expired ? EXPIRED_MESSAGE : null);

    async function submit(event: FormEvent) {
        event.preventDefault();
        setPending(true);
        setError(null);

        try {
            const payload =
                mode === 'login'
                    ? ((await api.json(apiPath('/api/auth/login'), {
                          method: 'POST',
                          body: { email, password },
                      })) as AuthResponse | null)
                    : ((await api.json('/api/auth/register', {
                          method: 'POST',
                          body: { email, password },
                      })) as AuthResponse | null);

            if (!payload?.token) {
                throw new ApiError(500, 'The server did not return a session token.');
            }

            // Clears the expiry flag too, so the notice does not outlive it.
            saveSession({ token: payload.token, roles: payload.roles ?? [] });
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Authentication failed.');
        } finally {
            setPending(false);
        }
    }

    return (
        <main style={{ fontFamily: 'system-ui', maxWidth: 360, margin: '4rem auto' }}>
            <h1>Lone Wolf</h1>
            <h2>{mode === 'login' ? 'Sign in' : 'Create account'}</h2>

            <form onSubmit={submit}>
                <label>
                    Email
                    <input
                        type="email"
                        required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        style={{ width: '100%', marginBottom: '0.75rem' }}
                    />
                </label>
                <label>
                    Password
                    <input
                        type="password"
                        required
                        minLength={8}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        style={{ width: '100%', marginBottom: '0.75rem' }}
                    />
                </label>

                {notice && (
                    <p role="alert" style={{ color: '#b00020' }}>
                        {notice}
                    </p>
                )}

                <button type="submit" disabled={pending} style={{ width: '100%' }}>
                    {pending ? 'Working…' : mode === 'login' ? 'Sign in' : 'Register'}
                </button>
            </form>

            <p style={{ marginTop: '1rem' }}>
                {mode === 'login' ? 'No account yet?' : 'Already registered?'}{' '}
                <button
                    type="button"
                    onClick={() => {
                        setMode(mode === 'login' ? 'register' : 'login');
                        setError(null);
                    }}
                >
                    {mode === 'login' ? 'Register' : 'Sign in'}
                </button>
            </p>
        </main>
    );
}
