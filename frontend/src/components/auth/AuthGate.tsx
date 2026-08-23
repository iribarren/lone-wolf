'use client';

/**
 * Compact authentication gate for the player app (US2). Renders the
 * login/registration form until a session exists, then passes through.
 */
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';

import { ApiError, apiPath } from '@/lib/api/client';
import { useApiClient } from '@/lib/hooks/useApiClient';
import { loadSession, saveSession } from '@/lib/auth';

interface AuthResponse {
    token?: string;
    roles?: string[];
}

export default function AuthGate({ children }: { children: ReactNode }) {
    const api = useApiClient();
    const [authenticated, setAuthenticated] = useState(false);
    const [mode, setMode] = useState<'login' | 'register'>('login');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState(false);

    useEffect(() => {
        setAuthenticated(loadSession() !== null);
    }, []);

    if (authenticated) {
        return <>{children}</>;
    }

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

            saveSession({ token: payload.token, roles: payload.roles ?? [] });
            setAuthenticated(true);
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

                {error && (
                    <p role="alert" style={{ color: '#b00020' }}>
                        {error}
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
