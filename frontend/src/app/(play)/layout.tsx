import AuthGate from '@/components/auth/AuthGate';

/**
 * Every route in the (play) group requires a session (US2 player surface).
 */
export default function PlayLayout({ children }: { children: React.ReactNode }) {
    return <AuthGate>{children}</AuthGate>;
}
