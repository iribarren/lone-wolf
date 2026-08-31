#!/usr/bin/env bash
#
# .env and .env.* are gitignored (".gitignore": secrets stay local), so a fresh
# checkout has neither the compose env_file nor Symfony's dotenv defaults.
# This writes the same non-secret local values .env.dist documents. Existing
# files are left untouched, so it is safe to run on a developer machine too.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [ ! -f "$ROOT/.env" ]; then
    cp "$ROOT/.env.dist" "$ROOT/.env"
    echo "created .env from .env.dist"
fi

if [ ! -f "$ROOT/backend/.env" ]; then
    cat > "$ROOT/backend/.env" <<'ENV'
# Written by .github/scripts/write-env.sh — non-secret defaults for CI and for
# a fresh checkout. Compose overrides DATABASE_URL for the containerised stack.
APP_ENV=dev
APP_SECRET=change_me_dev_secret
DEFAULT_URI=http://localhost:8080

DATABASE_URL=postgresql://lone_wolf:change_me_local@postgres:5432/lone_wolf?serverVersion=17&charset=utf8

CORS_ALLOW_ORIGIN=^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change_me_jwt
ENV
    echo "created backend/.env"
fi
