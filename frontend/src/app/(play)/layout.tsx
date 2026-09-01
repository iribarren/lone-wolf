import AuthGate from '@/components/auth/AuthGate';
import SignOutButton from '@/components/auth/SignOutButton';

/**
 * Every route in the (play) group requires a session (US2 player surface).
 * The sign-out control sits inside the gate, so it renders on every
 * authenticated page and disappears with the session.
 */
export default function PlayLayout({ children }: { children: React.ReactNode }) {
    return (
        <AuthGate>
            <SignOutButton />
            {children}
        </AuthGate>
    );
}
