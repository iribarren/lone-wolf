'use client';

/**
 * Sign-out control for the player app (B4). Lives in the (play) layout, so it
 * is reachable from every authenticated page.
 */
import { useQueryClient } from '@tanstack/react-query';

import { clearSession } from '@/lib/auth';
import Button from '@/components/ui/Button';
import PageShell from '@/components/ui/PageShell';


export default function SignOutButton() {
    const queryClient = useQueryClient();

    function signOut(): void {
        clearSession();
        // Drop every cached response with it, so the next player to sign in on
        // this browser cannot see the previous one's campaigns.
        queryClient.clear();
    }

    return (
        <PageShell as="div" variant="bar">
            <Button variant="ghost" onClick={signOut}>
                Sign out
            </Button>
        </PageShell>
    );
}
