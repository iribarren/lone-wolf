'use client';

/**
 * Sign-out control for the player app (B4). Lives in the (play) layout, so it
 * is reachable from every authenticated page.
 */
import { useQueryClient } from '@tanstack/react-query';

import { clearSession } from '@/lib/auth';

export default function SignOutButton() {
    const queryClient = useQueryClient();

    function signOut(): void {
        clearSession();
        // Drop every cached response with it, so the next player to sign in on
        // this browser cannot see the previous one's campaigns.
        queryClient.clear();
    }

    return (
        <div style={{ fontFamily: 'system-ui', maxWidth: 640, margin: '1rem auto 0', textAlign: 'right' }}>
            <button type="button" onClick={signOut}>
                Sign out
            </button>
        </div>
    );
}
