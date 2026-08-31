# 02 · Restore access to the admin backoffice

Wave 1 · after `01-ci-pipeline` · branch `fix-admin-url` · ~2 h · fixes audit finding **A1** (critical)

<context>
Lone Wolf is a multi-system solo-TTRPG assistant. Admins author "game systems" as a graph of
named stages with per-stage guidance; players run campaigns along that graph, journal per stage,
consult weighted random tables ("oracles"), and roll dice. Monorepo: `backend/` is Symfony 7.4 +
API Platform in hexagonal DDD by bounded context, `frontend/` is Next.js talking to it only
through the OpenAPI contract. The admin backoffice is EasyAdmin, server-rendered inside the
backend, behind a session firewall at `/admin/login`.

Read before changing anything:
- `AGENTS.md` — delivery rules (task = commit, checkpoint = PR, six merge gates)
- `.specify/memory/constitution.md` — the six principles that supersede every other convention
- `docs/audit/spec-compliance.md` §6 finding A1 — the audit entry for this defect
- `docker/nginx/default.conf` and `backend/src/Rulesets/Infrastructure/Admin/DashboardController.php`
</context>

<preconditions>
The stack must be running — this defect only reproduces over HTTP and cannot be seen from the
source alone:

    docker compose up -d --build
    docker compose exec php composer install
    docker compose exec php bin/console doctrine:migrations:migrate -n
    docker compose exec php bin/console app:create-admin --email=admin@example.test --password='admin-passphrase'
    docker compose exec php bin/console app:seed:demo

Confirm `make lint` and `make test` are green before you start.
</preconditions>

<problem>
The EasyAdmin backoffice is unreachable in a browser at the URL the README and
`docs/architecture.md` both document. `http://localhost:8080/admin` answers `301` to
`http://localhost/admin/` — the `:8080` port is dropped — which lands on whatever happens to be
serving port 80 on the host, or nothing. Signing in successfully at `/admin/login` redirects to
that same dead URL, so an admin can authenticate and still never reach the dashboard.

Root cause, in two parts:

1. `backend/public/admin/flow-editor.js` is a real file, so `backend/public/admin/` is a real
   directory that shadows the `/admin` route. It was added by commit `5459d2b` (the campaign-flows
   editor increment) and is registered in
   `backend/src/Rulesets/Infrastructure/Admin/DashboardController.php:27-30` via
   `configureAssets()->addJsFile('admin/flow-editor.js')`.

2. `docker/nginx/default.conf` routes with `try_files $uri $uri/ /index.php$is_args$args;`. The
   `$uri/` term matches that directory, so nginx issues its own directory-style redirect before
   Symfony is ever reached. With nginx's default `absolute_redirect on`, the redirect URL is
   rebuilt from the server's own listen port — 80 inside the container — not from the published
   port 8080.

Evidence captured 2026-08-30 against a running stack:

    $ curl -s -o /dev/null -w '%{http_code} %{redirect_url}' localhost:8080/admin
    301 http://localhost/admin/

    # ...and identically when the Host header already carries the port:
    $ curl -s -o /dev/null -H 'Host: localhost:8080' -w '%{redirect_url}' localhost:8080/admin
    http://localhost/admin/

    # /api has no shadowing directory and reaches Symfony normally:
    $ curl -s -o /dev/null -w '%{http_code} %{redirect_url}' localhost:8080/api
    401

    # Playwright, after a successful sign-in at /admin/login:
    AFTER LOGIN URL: http://localhost/admin/
    BODY: Not Found | Apache/2.4.66 (Ubuntu) Server at localhost Port 80

Why it matters: this makes the entire authoring surface inaccessible, which is why user story
US1 fails its own independent test and why FR-030 holds only in the negative. It is also the
reason findings A2, A3 and A4 went unnoticed — nobody could open the pages they are on.
</problem>

<instructions>
1. Confirm the diagnosis still holds before changing anything. Run the three `curl` commands
   above and check that `backend/public/admin/` still exists and that `default.conf` still uses
   `try_files $uri $uri/`. If the behaviour has changed, stop and report what you found instead
   of applying a fix for a problem that is gone.

2. Write the failing test first. Add `frontend/tests/e2e/admin.spec.ts` with a case that
   navigates to `http://localhost:8080/admin`, signs in through the form at `/admin/login`
   (fields `_username` and `_password`), and asserts that the resulting URL is still on port 8080
   and that the page shows the dashboard menu — "Game systems", "Campaign flows", "Oracles".
   Confirm it fails for the reason above before you fix anything.

3. Remove the route shadowing. Move `backend/public/admin/flow-editor.js` somewhere that does not
   collide with the `/admin` route — `backend/public/assets/admin-flow-editor.js` is the obvious
   choice — and update the `addJsFile()` call in `DashboardController::configureAssets()` to
   match. Delete the now-empty `backend/public/admin/` directory.

4. Defend against the class of bug, not just this instance. Add `absolute_redirect off;` to the
   `server` block in `docker/nginx/default.conf`, with a one-line comment explaining that it
   keeps nginx-generated redirects relative so the published port survives. Any future file under
   `public/` that shadows a route will then degrade gracefully instead of breaking a whole
   surface.

5. Restart nginx (`docker compose restart nginx`) and confirm the new E2E test passes.

6. Update the documentation in the same change set (Constitution VI). `README.md`,
   `docs/architecture.md` and `docs/functional-guide.md` all describe the backoffice URL;
   `docs/functional-guide.md` §3 and §9 additionally carry an explicit "Known defect" note and a
   troubleshooting row about this bug, both of which must be removed now that it is fixed.
</instructions>

<constraints>
- Out of scope: the flow-editor JavaScript's own bugs (findings A2 and A3) — those are prompt
  `03-fix-flow-editor.md`. Move the file, do not modify its contents.
- Out of scope: the missing oracle-entries field (A4), prompt 05.
- Do not change the Symfony routing, the security firewall, or `access_control`. Authorization is
  correct and is proven by `backend/tests/Integration/Identity/AdminBackofficeLoginTest.php` —
  the defect is entirely in asset placement and nginx redirect behaviour.
- Do not restructure `docker/nginx/default.conf` beyond the single directive.
</constraints>

<acceptance_criteria>
    # the documented URL reaches the dashboard rather than redirecting off-port
    curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' localhost:8080/admin
    # expected: a 302 to the login form, or a 200 — and NO redirect to a port-less host

    curl -s -o /dev/null -w '%{http_code}\n' localhost:8080/assets/admin-flow-editor.js
    # expected: 200

    cd frontend && npm run test:e2e
    # expected: the new admin.spec.ts case passes, and play.spec.ts still passes

    make lint && make test
    # expected: green, including AdminBackofficeLoginTest

Manually: open `http://localhost:8080/admin` in a browser, sign in, and land on a dashboard
showing the three menu sections. The URL bar still says `localhost:8080`.

No occurrence of the A1 "Known defect" wording remains in `docs/functional-guide.md`.
</acceptance_criteria>

<completion>
Branch `fix-admin-url` off an updated `master`. Commit atomically with short imperative subjects;
one logical change per commit (`AGENTS.md`: "Task = commit"). The failing test lands before the
fix.

Before finishing, run and report `make lint`, `make test`, and `npm run test:e2e`.

If a gate fails, report its output verbatim and stop. Never weaken, skip or delete a test to make
a suite pass — if a test genuinely blocks you, quarantine it with an explicit skip plus an
explanation in the PR description. Do not create or push git remotes.

Report: what you changed, which gates you ran, and anything you could not verify.
</completion>
