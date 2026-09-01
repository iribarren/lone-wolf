/**
 * Hand-written typed fetch wrapper around the generated OpenAPI schema
 * (T024). All backend calls MUST go through `apiFetch` — raw `fetch` to API
 * URLs is prohibited elsewhere (Constitution V).
 */
import type { components, paths } from './schema.gen';

export type ApiSchemas = components['schemas'];
export type ApiPaths = paths;
export type ApiPath = keyof ApiPaths;

/** Casts an interpolated runtime path to the generated path-key union. */
export function apiPath(path: string): ApiPath {
    return path as ApiPath;
}

const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';

export class ApiError extends Error {
    public constructor(
        public readonly status: number,
        public readonly title: string,
        public readonly detail?: string,
        public readonly violations?: readonly { property: string; message: string }[],
        public readonly extra?: Readonly<Record<string, unknown>>,
    ) {
        super(detail ? `${title}: ${detail}` : title);
        this.name = 'ApiError';
    }

    /** Parses an RFC 7807 problem document (or any error body) into ApiError. */
    public static async fromResponse(response: Response): Promise<ApiError> {
        let payload: Record<string, unknown> = {};
        try {
            payload = (await response.json()) as Record<string, unknown>;
        } catch {
            // non-JSON error body
        }

        const violations = Array.isArray(payload['violations'])
            ? (payload['violations'] as { property: string; message: string }[])
            : undefined;

        const known = new Set(['title', 'detail', 'violations', 'status', 'type', 'instance']);
        const extra: Record<string, unknown> = {};
        for (const [key, value] of Object.entries(payload)) {
            if (!known.has(key)) {
                extra[key] = value;
            }
        }

        return new ApiError(
            response.status,
            typeof payload['title'] === 'string' ? payload['title'] : `HTTP ${response.status}`,
            typeof payload['detail'] === 'string' ? payload['detail'] : undefined,
            violations,
            extra,
        );
    }
}

export interface ApiClientOptions {
    /** Resolves the bearer token, when the visitor is authenticated. */
    getToken?: () => string | null;
}

type Json = Record<string, unknown>;

export class ApiClient {
    public constructor(private readonly options: ApiClientOptions = {}) {}

    public async request<P extends keyof ApiPaths>(
        path: P,
        init?: Omit<RequestInit, 'body'> & { body?: unknown },
    ): Promise<Response> {
        const headers = new Headers(init?.headers);
        headers.set('Accept', 'application/json');

        if (init?.body !== undefined) {
            headers.set('Content-Type', 'application/json');
        }

        // Any falsy token means "no token": an absent `getToken` yields
        // `undefined`, which must omit the header rather than send the literal
        // string "Bearer undefined" (C1).
        const token = this.options.getToken?.();
        if (token && !headers.has('Authorization')) {
            headers.set('Authorization', `Bearer ${token}`);
        }

        const response = await fetch(`${BASE_URL}${String(path)}`, {
            ...init,
            headers,
            body: init?.body === undefined ? undefined : JSON.stringify(init.body),
        });

        if (!response.ok) {
            throw await ApiError.fromResponse(response);
        }

        return response;
    }

    /** JSON-body convenience variant for endpoints returning a payload. */
    public async json<P extends keyof ApiPaths>(path: P, init?: Parameters<ApiClient['request']>[1]): Promise<unknown> {
        const response = await this.request(path, init);
        const text = await response.text();

        return text === '' ? null : (JSON.parse(text) as Json);
    }
}
