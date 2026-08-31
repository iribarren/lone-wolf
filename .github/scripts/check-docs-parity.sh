#!/usr/bin/env bash
#
# Merge gate 6 (AGENTS.md) / Constitution VI — Documentation Parity:
# "Documentation updated in the same change set".
#
# A pull request that changes application code must also change at least one
# document (Markdown anywhere, or anything under docs/ or specs/). Nothing
# else about the documentation is judged here; that stays with the reviewer —
# this gate only guarantees the question was asked.
#
# Adding or repairing a test is never the change that invalidates a document,
# so backend/tests and frontend/tests do not count as code here.
#
# A change that genuinely has no documentation to move is waived by putting
# the "docs:none" label on the pull request. The waiver is announced in this
# log and visible on the PR itself, so a reviewer can challenge it — unlike a
# throwaway edit to README.md, which is the alternative a gate with no valve
# quietly encourages.
set -euo pipefail

WAIVER_LABEL="docs:none"

BASE_REF="${BASE_REF:?BASE_REF (the PR base branch) must be set}"

git fetch --no-tags origin "+refs/heads/${BASE_REF}:refs/remotes/origin/${BASE_REF}"
BASE="$(git merge-base "origin/${BASE_REF}" HEAD)"

CHANGED="$(git diff --name-only "$BASE" HEAD)"

CODE="$(echo "$CHANGED" | grep -E '^(backend/(src|config|migrations|features)/|frontend/(src|app|components|lib)/|scripts/)' || true)"
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

if printf '%s' "${PR_LABELS:-}" | tr ',' '\n' | grep -Fqx "$WAIVER_LABEL"; then
    cat <<MSG
Gate 6 WAIVED by the "${WAIVER_LABEL}" label — the author asserts this change
set has no documentation to move. Reviewer: confirm that, or remove the label.

Application code changed:
$(echo "$CODE" | sed 's/^/  /')
MSG
    exit 0
fi

cat >&2 <<MSG
GATE 6 FAILED — Documentation Parity (Constitution VI).

This pull request changes application code:
$(echo "$CODE" | sed 's/^/  /')

but no document changed with it. Update the affected spec under specs/, the
matching page under docs/, or the relevant Markdown (AGENTS.md, README.md,
quickstart.md …) in this same change set.

If this change genuinely has nothing to document, label the pull request
"${WAIVER_LABEL}" — the waiver is recorded in this log for the reviewer.
Do not satisfy this gate with a cosmetic documentation edit.
MSG
exit 1
