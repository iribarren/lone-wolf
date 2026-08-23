/**
 * JWT bearer attach + token storage helpers (T025).
 * Tokens live in localStorage for the solo-player PWA use case.
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
}

export function clearSession(): void {
    safeStorage()?.removeItem(TOKEN_KEY);
    safeStorage()?.removeItem(ROLES_KEY);
}

export function hasRole(role: string): boolean {
    return loadSession()?.roles.includes(role) ?? false;
}
