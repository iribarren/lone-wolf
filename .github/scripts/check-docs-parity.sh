#!/usr/bin/env bash
#
# Merge gate 6 (AGENTS.md) / Constitution VI — Documentation Parity:
# "Documentation updated in the same change set".
#
# A pull request that changes application code must also change at least one
# document (Markdown anywhere, or anything under docs/ or specs/). Nothing
# else about the documentation is judged here; that stays with the reviewer.
set -euo pipefail

BASE_REF="${BASE_REF:?BASE_REF (the PR base branch) must be set}"

git fetch --no-tags origin "+refs/heads/${BASE_REF}:refs/remotes/origin/${BASE_REF}"
BASE="$(git merge-base "origin/${BASE_REF}" HEAD)"

CHANGED="$(git diff --name-only "$BASE" HEAD)"

CODE="$(echo "$CHANGED" | grep -E '^(backend/(src|config|migrations|features|tests)/|frontend/(src|app|components|lib|tests)/|scripts/)' || true)"
DOCS="$(echo "$CHANGED" | grep -E '(\.md$|^docs/|^specs/)' || true)"

if [ -z "$CODE" ]; then
    echo "Gate 6 OK: no application code in this change set."
    exit 0
fi

if [ -n "$DOCS" ]; then
    echo "Gate 6 OK: documentation moves with the code."
    echo "$DOCS" | sed 's/^/  /'
    exit 0
fi

cat >&2 <<MSG
GATE 6 FAILED — Documentation Parity (Constitution VI).

This pull request changes application code:
$(echo "$CODE" | sed 's/^/  /')

but no document changed with it. Update the affected spec under specs/, the
matching page under docs/, or the relevant Markdown (AGENTS.md, README.md,
quickstart.md …) in this same change set.
MSG
exit 1
