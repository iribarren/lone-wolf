'use client';

/**
 * React binding between TanStack Query components and the typed API client
 * (T025). The client attaches the stored JWT on every request.
 */
import { useMemo } from 'react';

import { ApiClient } from '@/lib/api/client';
import { loadSession } from '@/lib/auth';

export function useApiClient(): ApiClient {
    return useMemo(
        () =>
            new ApiClient({
                getToken: () => loadSession()?.token ?? null,
            }),
        [],
    );
}
