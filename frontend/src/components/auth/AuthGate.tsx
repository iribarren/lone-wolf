'use client';

/**
 * Compact authentication gate for the player app (US2). Renders the
 * login/registration form until a session exists, then passes through.
 */
import { useState, useSyncExternalStore, type FormEvent, type ReactNode } from 'react';

import { ApiError, type ApiSchemas } from '@/lib/api/client';
import { useApiClient } from '@/lib/hooks/useApiClient';
import { loadSession, saveSession, sessionExpired, subscribeToSession } from '@/lib/auth';


import styles from './AuthGate.module.css';
import Banner from '@/components/ui/Banner';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import PageShell from '@/components/ui/PageShell';
const EXPIRED_MESSAGE = 'Your session expired. Sign in to continue.';

/**
 * Both auth endpoints answer with the contract's AuthToken. The login path is
 * generated like every other one now that the OpenAPI document carries it
 * (C2) — no `apiPath()` cast stands between this form and the contract.
 */
type AuthResponse = Partial<ApiSchemas['AuthToken']>;

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

    // The gate outlives the sessions it guards, so when one ends the form has
    // to go back to what a first-time visitor sees rather than keep the last
    // player's mode and typed credentials. Adjusting state during render (the
    // React-documented pattern) avoids showing that stale form for a frame.
    const [wasAuthenticated, setWasAuthenticated] = useState(authenticated);
    if (wasAuthenticated !== authenticated) {
        setWasAuthenticated(authenticated);

        if (!authenticated) {
            setMode('login');
            setEmail('');
            setPassword('');
            setError(null);
        }
    }

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
                    ? ((await api.json('/api/auth/login', {
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
        <PageShell variant="form">
            <h1>Lone Wolf</h1>
            <h2>{mode === 'login' ? 'Sign in' : 'Create account'}</h2>

            <form onSubmit={submit}>
                <label>
                    Email
                    <Input
                        type="email"
                        required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className={styles.field}
                    />
                </label>
                <label>
                    Password
                    <Input
                        type="password"
                        required
                        minLength={8}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        className={styles.field}
                    />
                </label>

                {notice && (
                    <Banner variant="danger" role="alert" className={styles.error}>
                        <p>{notice}</p>
                    </Banner>
                )}

                <Button
                    type="submit"
                    variant="primary"
                    pending={pending}
                    pendingLabel="Working…"
                    className={styles.submit}
                >
                    {mode === 'login' ? 'Sign in' : 'Register'}
                </Button>
            </form>

            <p className={styles.footer}>
                {mode === 'login' ? 'No account yet?' : 'Already registered?'}{' '}
                <Button
                    variant="ghost"
                    onClick={() => {
                        setMode(mode === 'login' ? 'register' : 'login');
                        setError(null);
                    }}
                >
                    {mode === 'login' ? 'Register' : 'Sign in'}
                </Button>
            </p>
        </PageShell>
    );
}
