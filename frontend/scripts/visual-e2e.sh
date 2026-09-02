#!/usr/bin/env bash
# Runs the Playwright `visual` project inside a pinned container.
#
# Why not just run it on the host: the baselines are pixel comparisons of
# rendered text, and text rasterisation depends on the machine's freetype,
# fontconfig and font packages — not on the browser, which Playwright already
# pins. Baselines taken on a developer's distro and compared on a CI runner
# disagree by a hairline of antialiasing along every glyph, and a suite that is
# red for reasons nobody can act on gets switched off. So one image renders
# them everywhere: locally, and in `.github/workflows/ci.yml`.
#
# The image tag must track `@playwright/test` in package.json — the container
# supplies the browser, the mounted node_modules supplies the runner, and a
# mismatch between them is refused below rather than debugged later.
#
# Usage:
#   scripts/visual-e2e.sh                      compare against the baselines
#   scripts/visual-e2e.sh --update-snapshots   rewrite them (see the docs first)
set -euo pipefail

frontend="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$frontend"

version="$(node -p "require('./package.json').devDependencies['@playwright/test'].replace(/[^0-9.]/g, '')")"
image="mcr.microsoft.com/playwright:v${version}-noble"

if [ ! -d node_modules ]; then
    echo "visual-e2e: node_modules is missing — run 'npm ci' in frontend/ first." >&2
    exit 1
fi

# The stack has to be up already: this script drives a browser, it does not
# boot an app. --network host is what lets the container reach it on the
# host's own ports, and is also why no port mapping is needed.
base_url="${E2E_BASE_URL:-http://localhost:3000}"

if ! curl --silent --fail --max-time 5 --output /dev/null "$base_url"; then
    echo "visual-e2e: nothing answering at $base_url — start the stack with 'make up' first." >&2
    exit 1
fi

# Run as the invoking user so the baselines and any diff artifacts are not
# written into the working tree owned by root.
exec docker run --rm \
    --network host \
    --ipc host \
    --user "$(id -u):$(id -g)" \
    --volume "$frontend:/work" \
    --workdir /work \
    --env HOME=/tmp \
    --env CI \
    --env "E2E_BASE_URL=$base_url" \
    "$image" \
    npx playwright test --project=visual "$@"
