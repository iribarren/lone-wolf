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
```

Expected: backend health responds at <http://localhost:8080/api/health> with
`{"status":"ok"}`; frontend placeholder renders at <http://localhost:3000>.

## Common commands

```bash
make up        # boot the whole stack
make logs      # tail all service logs
make console   # open a shell in the php container
make test      # backend PHPUnit suites + frontend Vitest
make lint      # PHPStan + deptrac layer rules
make npm CMD="install"
```

## Documentation

- Governance: `.specify/memory/constitution.md`
- Feature spec: `specs/001-solo-ttrpg-assistant/`
- Architecture notes: `docs/architecture.md`

## License

Private project — all rights reserved.
