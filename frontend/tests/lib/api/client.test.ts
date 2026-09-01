import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiClient, apiPath } from '@/lib/api/client';

function jsonResponse(status: number, body: unknown = {}): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

/** Returns the headers the client actually put on the wire. */
function sentHeaders(fetchMock: ReturnType<typeof vi.fn>): Headers {
    const init = fetchMock.mock.calls[0]?.[1] as RequestInit | undefined;

    return new Headers(init?.headers);
}

describe('ApiClient bearer header', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, []));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('omits the Authorization header entirely when constructed with no getToken', async () => {
        await new ApiClient().request('/api/campaigns');

        expect(sentHeaders(fetchMock).has('Authorization')).toBe(false);
    });

    it('never sends the literal string "Bearer undefined"', async () => {
        await new ApiClient().request('/api/campaigns');

        expect(sentHeaders(fetchMock).get('Authorization')).not.toBe('Bearer undefined');
    });

    it('omits the header when getToken resolves to null — the signed-out case', async () => {
        await new ApiClient({ getToken: () => null }).request('/api/campaigns');

        expect(sentHeaders(fetchMock).has('Authorization')).toBe(false);
    });

    it('omits the header when getToken resolves to an empty string', async () => {
        await new ApiClient({ getToken: () => '' }).request('/api/campaigns');

        expect(sentHeaders(fetchMock).has('Authorization')).toBe(false);
    });

    it('attaches the bearer token when one is stored', async () => {
        await new ApiClient({ getToken: () => 'a-real-token' }).request('/api/campaigns');

        expect(sentHeaders(fetchMock).get('Authorization')).toBe('Bearer a-real-token');
    });
});

describe('ApiClient 401 handling', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('notifies onUnauthorized when a request 401s', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(401, { title: 'Unauthorized' })));
        const onUnauthorized = vi.fn();

        await expect(
            new ApiClient({ getToken: () => 'stale', onUnauthorized }).request('/api/campaigns'),
        ).rejects.toThrow();

        expect(onUnauthorized).toHaveBeenCalledTimes(1);
    });

    it('does not treat a 401 from the login endpoint as an expiry — that is a wrong password', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(jsonResponse(401, { detail: 'Invalid email or password.' })),
        );
        const onUnauthorized = vi.fn();

        await expect(
            new ApiClient({ onUnauthorized }).request(apiPath('/api/auth/login'), {
                method: 'POST',
                body: { email: 'a@example.test', password: 'wrong' },
            }),
        ).rejects.toThrow();

        expect(onUnauthorized).not.toHaveBeenCalled();
    });

    it('does not treat a 401 from the register endpoint as an expiry', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(401, {})));
        const onUnauthorized = vi.fn();

        await expect(
            new ApiClient({ onUnauthorized }).request('/api/auth/register', {
                method: 'POST',
                body: { email: 'a@example.test', password: 'nope' },
            }),
        ).rejects.toThrow();

        expect(onUnauthorized).not.toHaveBeenCalled();
    });

    it('leaves other error statuses alone', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, { title: 'Unprocessable' })));
        const onUnauthorized = vi.fn();

        await expect(
            new ApiClient({ getToken: () => 'live', onUnauthorized }).request('/api/campaigns'),
        ).rejects.toThrow();

        expect(onUnauthorized).not.toHaveBeenCalled();
    });
});
