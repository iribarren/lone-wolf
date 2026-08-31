# 08 · Complete the player session lifecycle

Wave 4 · after `01-ci-pipeline` · branch `session-lifecycle` · ~half a day · fixes audit finding **B4** (medium) and **C1**

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Players sign in to the Next.js app and run solo
campaigns against the Symfony backend, authenticating with a JWT bearer token. Monorepo:
`backend/` is Symfony 7.4 + API Platform, `frontend/` is Next.js talking to it only through the
generated client in `frontend/src/lib/api/`.

Auth model as built: `POST /api/auth/register` and `POST /api/auth/login` return
`{token, roles}`. Tokens are RS256, **one-hour TTL** with a 60-second clock-skew allowance
(`backend/config/packages/lexik_jwt_authentication.yaml`). The token is stored in `localStorage`
and attached as a bearer header on every request. There is no refresh token by design.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `docs/audit/spec-compliance.md` §6 findings B4 and C1
- `frontend/src/lib/auth.ts`, `frontend/src/lib/api/client.ts`,
  `frontend/src/components/auth/AuthGate.tsx`, `frontend/src/app/(play)/layout.tsx`
</context>

<preconditions>
The stack must be running and seeded:

    docker compose up -d --build
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
Three related gaps in how the player app handles its own session.

**1. There is no way to sign out.** `frontend/src/lib/auth.ts` exports `clearSession()` and
`hasRole()`, and neither is called anywhere in the application. To switch accounts a user must
clear the site's local storage by hand.

**2. An expired token still presents as "signed in".** `AuthGate.tsx` decides authentication with

    useEffect(() => {
        setAuthenticated(loadSession() !== null);
    }, []);

which tests only that a token *string* is present. After the one-hour TTL the token is still
there, so the gate keeps rendering the app while every request fails. There is no 401 handling
anywhere: `ApiClient.request` throws a generic `ApiError` and each query surfaces its own error,
so the user sees a screen of unrelated failures rather than "your session expired, sign in again".

**3. `Authorization: Bearer undefined` (finding C1).** In `frontend/src/lib/api/client.ts:84`:

    const token = this.options.getToken?.();
    if (token !== null && !headers.has('Authorization')) {
        headers.set('Authorization', `Bearer ${token}`);
    }

When `getToken` is absent the optional call yields `undefined`, which passes `!== null`, and the
literal string `Bearer undefined` is sent. This is latent today only because `useApiClient()`
always supplies a `getToken` that returns `null` — a single direct `new ApiClient()` anywhere
would ship a malformed header.

Why it matters: FR-030 requires players to access only player-facing features and their own data,
and the sign-in gate is the mechanism. A gate that cannot be exited and cannot tell a live
session from a dead one is an incomplete implementation of it. This is also the single most
likely source of confusing bug reports from real use, because the failure appears an hour after
the cause.
</problem>

<pattern>
`frontend/src/lib/auth.ts` already has everything the storage layer needs — `loadSession`,
`saveSession`, `clearSession`, `hasRole` — all wrapped in a `safeStorage()` try/catch for SSR.
Do not add a second storage abstraction; wire up what is there.

`ApiClient.request` in `frontend/src/lib/api/client.ts` is the single choke point every call
passes through, and it already centralises the `Accept` header, the `Content-Type` header, the
bearer header and error mapping. The 401 handler belongs there, not sprinkled across call sites.
It already ends with:

    if (!response.ok) {
        throw await ApiError.fromResponse(response);
    }

`ApiError` carries `status`, so the branch you need is a `status === 401` check in that block.

`AuthGate.tsx` owns the authenticated/unauthenticated switch for the whole `(play)` route group
via `frontend/src/app/(play)/layout.tsx`. The expiry path should end up back in exactly the state
a first-time visitor sees.
</pattern>

<instructions>
1. Confirm the diagnosis still holds. Grep for callers of `clearSession` and `hasRole`, read the
   three files above, and reproduce the expiry behaviour — the fastest way is to overwrite
   `localStorage['lone-wolf.token']` with a syntactically valid but expired or bogus token and
   reload. Confirm the app renders as signed-in and then fails per-query.

2. Write the failing tests first (Vitest):
   - `ApiClient` sends no `Authorization` header at all when constructed with no `getToken`, and
     never sends the literal string `Bearer undefined`
   - a 401 response clears the stored session
   - `AuthGate` renders the sign-in form, not the app, when a 401 has invalidated the session
   - signing out clears both storage keys and returns the user to the gate

3. Fix C1 in `client.ts`: treat any falsy token as "no token" so the header is omitted entirely
   rather than sent malformed.

4. Add 401 handling in `ApiClient.request`, in the `!response.ok` branch. On a 401 it must clear
   the session and notify the app so the gate re-renders. Choose a mechanism deliberately —
   a callback passed in through `ApiClientOptions` (symmetrical with `getToken`, and keeps
   `client.ts` free of React) is the natural fit for this codebase; a custom event or a small
   subscription would also work. Justify your choice in the report.

   Two things to get right: do **not** treat a 401 from `POST /api/auth/login` as an expiry —
   that is simply a wrong password and `AuthGate` already renders it. And clearing must be
   idempotent, because several TanStack queries can fail concurrently.

5. Make `AuthGate` trust the session store rather than a one-shot `useEffect`. After a 401 it must
   show the sign-in form with a clear message — something like "Your session expired. Sign in to
   continue." — distinct from a failed-credentials error. Preserve the existing `role="alert"`
   convention for errors.

6. Add a sign-out control. It belongs where a signed-in user can always reach it — the `(play)`
   layout is the natural home, since it wraps every authenticated page. It must be a real
   `<button>` with an accessible name, call `clearSession()`, clear the TanStack Query cache so
   the next user does not see the previous one's data, and return to the gate.

7. Add a Playwright case: register, confirm the app renders, sign out, confirm the gate returns
   and the campaign list is not reachable without signing in again.

8. Update `docs/functional-guide.md` in the same change set (Constitution VI). §5.1 states there
   is no sign-out control, §8 lists B4 as a known gap, and §9 has a troubleshooting row about
   everything 401-ing after an hour. Update all three to describe the real behaviour — note that
   the one-hour expiry itself remains, since there is no refresh token; what changes is that the
   app now handles it honestly.
</instructions>

<constraints>
- Backend changes are out of scope. The firewall, the token TTL, the clock skew and the
  `ROLE_PLAYER` grant are correct and covered by
  `backend/tests/Integration/Identity/AdminBackofficeLoginTest.php` and the unit suite.
- **Do not implement refresh tokens.** Absence of refresh is a deliberate design decision for a
  single-player app, recorded in `specs/001-solo-ttrpg-assistant/research.md` (R3). Changing it
  is a spec decision, not a bug fix — report it as a suggestion if you think it is warranted.
- **Do not move tokens out of `localStorage` in this PR.** The audit notes the XSS exposure, and
  `auth.ts` documents the choice ("Tokens live in localStorage for the solo-player PWA use
  case"). Moving to httpOnly cookies would change the backend auth flow and the contract, so it
  needs its own spec and its own PR.
- Do not add client-side JWT decoding to pre-empt expiry. Trust the server's 401; a clock you do
  not control is not a source of truth.
- Out of scope: password reset, account deletion, "remember me", and role-based rendering.
  `hasRole()` may stay unused — the admin surface is server-rendered — but say so explicitly in
  your report rather than leaving it ambiguous.
- Out of scope: visual design. Match the surrounding inline-style approach so prompts 18–19 can
  restyle everything at once.
</constraints>

<acceptance_criteria>
    npm run test && npm run typecheck && npm run lint
    npm run test:e2e
    make lint && make test

Manually, in the player app:
- a sign-out control is visible on every authenticated page; using it returns you to the sign-in
  form, and navigating back to `/campaigns` shows the gate rather than cached data
- corrupt `localStorage['lone-wolf.token']` and reload: the app shows the sign-in form with an
  expiry message, not a screen of failed queries
- a wrong password still shows a credentials error, **not** the expiry message
- after signing in as a second account, no data from the first is visible

In the browser network tab, an unauthenticated request carries **no** `Authorization` header —
never `Bearer undefined`.
</acceptance_criteria>

<completion>
Branch `session-lifecycle` off an updated `master`. Commit atomically with short imperative
subjects; one logical change per commit (`AGENTS.md`: "Task = commit"). Tests land before the
implementation.

Before finishing, run and report `make lint`, `make test`, `npm run typecheck`, `npm run lint`
and `npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, which 401-notification mechanism you chose and
why, whether `hasRole()` remains unused, and anything you could not verify.
</completion>
