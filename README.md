# Lone Wolf — Solo TTRPG Digital Assistant

A multi-system solo TTRPG digital assistant. Administrators author game systems
(Rulesets) and their Campaign Flows plus scoped oracles; players run guided solo
campaigns, journal per stage, consult weighted oracles, maintain system-shaped
character sheets, and roll dice with standard notation.

## Repository layout (monorepo)

```text
lone-wolf/
├── backend/    Symfony 7.4 LTS API (PHP 8.3+, hexagonal DDD by bounded context)
├── frontend/   Next.js (React) player app (TypeScript strict)
├── docker/     Shared infrastructure config (nginx, …)
├── specs/      Feature specifications (spec → plan → data-model → contracts → tasks)
└── docs/       Architecture notes & documentation
```

The two stacks are **entirely decoupled** (Constitution Principle V): the
frontend communicates with the backend **exclusively through the OpenAPI
contract** (`specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`), consumed
via a generated typed client — no direct database access, no session sharing,
no server-side templating. Errors use RFC 7807 `application/problem+json`.

## What's implemented (US1–US6)

- **Game systems & campaign flows** — admins author systems with named stages,
  legal transitions, a designated starting stage and per-stage pacing guidance;
  occupied stages are edit-guarded; deactivation never breaks running campaigns.
- **Guided solo campaigns** — pick a system, land on its opening stage with
  guidance and suggested actions, advance only along legal transitions (illegal
  moves explain the alternatives), stop/resume with exact state restoration,
  delete irreversibly behind typed confirmation.
- **Stage-grouped journal** — narrative entries stamped with the stage they were
  written on; keyset-paginated timeline stays fast at 500 entries.
- **Oracles** — admin-authored weighted tables scoped globally or to one system;
  in-play drawer consults for exactly one proportional result and can save it
  (with interpretation) into the journal; empty tables fail friendly.
- **System-shaped character sheets** — PC/NPC attributes validated field-by-field
  against the owning system's jsonb sheet structure; structure drift flags
  characters for review without ever silently altering data.
- **Dice roller** — strict `NdM±K` notation with pre-roll refusals that name the
  reason, every die shown plus modified total, optional log-to-journal.

Admin content ships via the EasyAdmin backoffice (`/admin`, `ROLE_ADMIN`) or the
demo seeder (`app:seed:demo`); players play at `http://localhost:3000`.
Architecture details: [docs/architecture.md](docs/architecture.md).

## Stack

| Layer    | Technology                                                        |
|----------|-------------------------------------------------------------------|
| Backend  | PHP 8.3+, Symfony 7.4 LTS, API Platform (REST + OpenAPI), Doctrine ORM/PostgreSQL (JSONB), EasyAdmin backoffice |
| Frontend | Next.js App Router, TypeScript strict, TanStack Query, generated openapi-typescript client |
| Tests    | PHPUnit 11 (pure unit — no kernel boot; integration), Behat (E2E in ubiquitous language), Vitest + Playwright |
| Quality  | PHPStan max level + strict rules, deptrac layer rules             |

## Quick start

```bash
cp .env.dist .env            # adjust local secrets; never commit .env
docker compose up --build    # boots php-fpm + nginx, postgres, next dev
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php bin/console app:create-admin   # backoffice account (ADMIN_EMAIL / ADMIN_PASSWORD)
docker compose exec php bin/console app:seed:demo      # optional: quickstart demo systems + oracles
```

Expected: backend health responds at <http://localhost:8080/api/health> with
`{"status":"ok"}`; the player app renders at <http://localhost:3000> behind its
sign-in gate; the EasyAdmin backoffice serves at <http://localhost:8080/admin>.

Verified boot state (Phase-9 parity pass):

```text
$ docker compose ps
SERVICE    STATUS
frontend   Up
nginx      Up
php        Up
postgres   Up (healthy)

$ curl -s localhost:8080/api/health
{"status":"ok"}
$ curl -o /dev/null -w "%{http_code}" localhost:3000
200
```

## Common commands

```bash
make up        # boot the whole stack
make logs      # tail all service logs
make console   # open a shell in the php container
make test      # backend PHPUnit suites + frontend Vitest
make lint      # PHPStan + deptrac layer rules
make npm CMD="install"
scripts/check-contract.sh   # Constitution V gate: runtime OpenAPI vs canonical contract
scripts/check-journal-performance.sh  # SC-008 evidence: 500-entry journal, latest view < 2 s
```

The contract-drift check requires the backend to be up (`docker compose up -d`)
and `python3` with PyYAML; it exits non-zero when the served `/api/docs.json`
diverges from `specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`.

## Documentation

- Governance: `.specify/memory/constitution.md`
- Feature spec: `specs/001-solo-ttrpg-assistant/`
- Architecture notes: `docs/architecture.md`

## License

Private project — all rights reserved.
