/**
 * Hand-written typed fetch wrapper around the generated OpenAPI schema
 * (T024). All backend calls MUST go through `apiFetch` — raw `fetch` to API
 * URLs is prohibited elsewhere (Constitution V).
 */
import type { components, paths } from './schema.gen';

export type ApiSchemas = components['schemas'];
export type ApiPaths = paths;

const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8080';

export class ApiError extends Error {
    public constructor(
        public readonly status: number,
        public readonly title: string,
        public readonly detail?: string,
        public readonly violations?: readonly { property: string; message: string }[],
    ) {
        super(detail ? `${title}: ${detail}` : title);
        this.name = 'ApiError';
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

        const token = this.options.getToken?.();
        if (token !== null && !headers.has('Authorization')) {
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
