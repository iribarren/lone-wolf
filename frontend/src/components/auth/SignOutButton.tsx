'use client';

/**
 * Sign-out control for the player app (B4). Lives in the (play) layout, so it
 * is reachable from every authenticated page.
 */
import { useQueryClient } from '@tanstack/react-query';

import { clearSession } from '@/lib/auth';


import styles from './SignOutButton.module.css';
export default function SignOutButton() {
    const queryClient = useQueryClient();

    function signOut(): void {
        clearSession();
        // Drop every cached response with it, so the next player to sign in on
        // this browser cannot see the previous one's campaigns.
        queryClient.clear();
    }

    return (
        <div className={styles.bar}>
            <button type="button" onClick={signOut}>
                Sign out
            </button>
        </div>
    );
}
