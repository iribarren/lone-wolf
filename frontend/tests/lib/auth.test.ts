import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    clearSession,
    expireSession,
    loadSession,
    saveSession,
    sessionExpired,
    subscribeToSession,
} from '@/lib/auth';

const TOKEN_KEY = 'lone-wolf.token';
const ROLES_KEY = 'lone-wolf.roles';

beforeEach(() => {
    window.localStorage.clear();
    clearSession();
});

afterEach(() => {
    window.localStorage.clear();
    clearSession();
});

describe('session store', () => {
    it('clears both storage keys on sign-out', () => {
        saveSession({ token: 't', roles: ['ROLE_PLAYER'] });

        clearSession();

        expect(window.localStorage.getItem(TOKEN_KEY)).toBeNull();
        expect(window.localStorage.getItem(ROLES_KEY)).toBeNull();
        expect(loadSession()).toBeNull();
    });

    it('does not flag a deliberate sign-out as an expiry', () => {
        saveSession({ token: 't', roles: [] });

        clearSession();

        expect(sessionExpired()).toBe(false);
    });

    it('clears the stored session and flags an expiry when a 401 arrives', () => {
        saveSession({ token: 't', roles: [] });

        expireSession();

        expect(loadSession()).toBeNull();
        expect(sessionExpired()).toBe(true);
    });

    it('is idempotent — concurrent 401s notify subscribers only once', () => {
        saveSession({ token: 't', roles: [] });
        const listener = vi.fn();
        const unsubscribe = subscribeToSession(listener);

        expireSession();
        expireSession();
        expireSession();

        expect(listener).toHaveBeenCalledTimes(1);
        unsubscribe();
    });

    it('drops the expiry flag once a new session is saved', () => {
        saveSession({ token: 't', roles: [] });
        expireSession();

        saveSession({ token: 'fresh', roles: [] });

        expect(sessionExpired()).toBe(false);
        expect(loadSession()?.token).toBe('fresh');
    });

    it('notifies subscribers when a session is saved or cleared', () => {
        const listener = vi.fn();
        const unsubscribe = subscribeToSession(listener);

        saveSession({ token: 't', roles: [] });
        expect(listener).toHaveBeenCalledTimes(1);

        clearSession();
        expect(listener).toHaveBeenCalledTimes(2);

        unsubscribe();
        clearSession();
        expect(listener).toHaveBeenCalledTimes(2);
    });
});
