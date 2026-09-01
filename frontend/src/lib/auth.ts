/**
 * JWT bearer attach + token storage helpers (T025).
 * Tokens live in localStorage for the solo-player PWA use case.
 *
 * The store is also the single source of truth for "am I signed in?" — it
 * publishes changes so `AuthGate` can re-render when the session ends, either
 * because the player signed out or because the server rejected the token
 * (B4). There is no refresh token by design (research.md R3), so an expiry is
 * terminal: the player signs in again.
 */

const TOKEN_KEY = 'lone-wolf.token';
const ROLES_KEY = 'lone-wolf.roles';

export interface StoredSession {
    token: string;
    roles: string[];
}

function safeStorage(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}

const listeners = new Set<() => void>();

/** True once the server has rejected the stored token; reset on a new session. */
let expired = false;

function notify(): void {
    for (const listener of [...listeners]) {
        listener();
    }
}

/** Subscribes to session changes; returns the unsubscribe function. */
export function subscribeToSession(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function loadSession(): StoredSession | null {
    const storage = safeStorage();
    const token = storage?.getItem(TOKEN_KEY);

    if (!token) {
        return null;
    }

    let roles: string[] = [];
    try {
        roles = JSON.parse(storage?.getItem(ROLES_KEY) ?? '[]') as string[];
    } catch {
        roles = [];
    }

    return { token, roles };
}

export function saveSession(session: StoredSession): void {
    safeStorage()?.setItem(TOKEN_KEY, session.token);
    safeStorage()?.setItem(ROLES_KEY, JSON.stringify(session.roles));
    expired = false;
    notify();
}

export function clearSession(): void {
    safeStorage()?.removeItem(TOKEN_KEY);
    safeStorage()?.removeItem(ROLES_KEY);
    expired = false;
    notify();
}

/**
 * Ends the session because the server answered 401. Idempotent: several
 * TanStack queries can fail concurrently, and the player should be told once.
 */
export function expireSession(): void {
    if (expired) {
        return;
    }

    safeStorage()?.removeItem(TOKEN_KEY);
    safeStorage()?.removeItem(ROLES_KEY);
    expired = true;
    notify();
}

/** Whether the last session ended by rejection rather than by signing out. */
export function sessionExpired(): boolean {
    return expired;
}

export function hasRole(role: string): boolean {
    return loadSession()?.roles.includes(role) ?? false;
}
