#!/usr/bin/env bash
#
# Requirements traceability gate: every functional requirement a feature spec
# ratifies must appear in that feature's traceability matrix, with an honest
# status and citations that are real.
#
# Why this gate exists: only 19 of this feature's 31 requirements were cited by
# any task, and five of the seven requirements the 2026-08-30 compliance audit
# found undelivered sat in the untraced set. Untraced and undelivered correlate
# almost exactly — a requirement no task claims is a requirement nobody checks
# at the end. This gate makes the next such requirement fail a build instead of
# surviving to an audit.
#
# Checks, for every `specs/*/spec.md`:
#   A) the sibling `traceability.md` exists, and every `FR-NNN` the spec defines
#      has exactly one matrix row (`| FR-NNN | ...`)
#   B) no matrix row names an `FR-NNN` the spec does not define
#   C) every row carries exactly one of the four legal statuses, and a
#      `NOT-IMPLEMENTED` row links at least one task id that is still OPEN
#      (`- [ ] TNNN`) in the same feature's `tasks.md` — an undelivered
#      requirement with no open task behind it is a requirement nobody owns
#   D) every backticked, repo-relative path cited anywhere in the matrix exists
#      on disk (the cited tests and implementations must be real files)
#
# A "cited path" is a backticked path under one of the known top-level
# directories, as in scripts/check-task-integrity.sh. Backticked prose that is
# not a path (`FR-002..FR-005`, `COVERED`, class names) is ignored by
# construction. A trailing `::method` or `#anchor` is stripped before the test,
# so a row may cite `Some/File.php::testCase` and still be checked as a file.
#
# Documented intentional exceptions (see EXEMPT_PATHS below):
#   - none. Add an entry only for a path that is legitimately absent (generated
#     at build time, or superseded by a later task), with a one-line rationale
#     here. Never add one to quiet a citation that was simply wrong: fix the
#     matrix instead.
#
# Requirements: bash, grep, sed, coreutils. No network, no stack, no toolchain.
#
# Usage: scripts/check-traceability.sh   # 0 = clean, 1 = drift, 2 = failure

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || {
    echo "check-traceability: cannot enter repository root." >&2
    exit 2
}

# Paths the matrix may cite even though they are not on disk. Keep empty.
EXEMPT_PATHS=()

is_exempt() {
    local candidate="$1" known
    for known in ${EXEMPT_PATHS[@]+"${EXEMPT_PATHS[@]}"}; do
        [ "$candidate" = "$known" ] && return 0
    done
    return 1
}

LEGAL_STATUSES="COVERED NO-TEST NO-TASK NOT-IMPLEMENTED"

shopt -s nullglob
SPECS=(specs/*/spec.md)
shopt -u nullglob

if [ ${#SPECS[@]} -eq 0 ]; then
    echo "check-traceability: no specs/*/spec.md found." >&2
    exit 2
fi

drift=()

for spec in "${SPECS[@]}"; do
    feature_dir="$(dirname "$spec")"
    matrix="$feature_dir/traceability.md"
    ledger="$feature_dir/tasks.md"

    echo "== $feature_dir"

    # --- A) the matrix must exist -------------------------------------------
    if [ ! -f "$matrix" ]; then
        drift+=("$feature_dir: no traceability.md — every requirement is untraced")
        continue
    fi

    # --- A/B) requirement coverage, both directions -------------------------
    spec_frs="$(grep -oE 'FR-[0-9]{3}' "$spec" | sort -u)"
    if [ -z "$spec_frs" ]; then
        drift+=("$spec: defines no FR-NNN requirements to trace")
        continue
    fi

    matrix_frs="$(grep -oE '^\| FR-[0-9]{3} ' "$matrix" | tr -d '|' | tr -d ' ' | sort)"

    while read -r fr; do
        [ -n "$fr" ] || continue
        rows="$(grep -c "^| $fr " "$matrix" || true)"
        case "$rows" in
        0) drift+=("$matrix: $fr is ratified in spec.md but has no matrix row") ;;
        1) ;;
        *) drift+=("$matrix: $fr has $rows matrix rows (expected exactly 1)") ;;
        esac
    done <<<"$spec_frs"

    while read -r fr; do
        [ -n "$fr" ] || continue
        grep -q "$fr" <<<"$spec_frs" ||
            drift+=("$matrix: row $fr names a requirement spec.md does not define")
    done <<<"$matrix_frs"

    printf '   %-32s %2d requirements / %2d rows\n' \
        "spec.md ↔ traceability.md" \
        "$(wc -l <<<"$spec_frs")" \
        "$(grep -c '^| FR-' "$matrix" || true)"

    # --- C) status vocabulary, and open tasks behind NOT-IMPLEMENTED --------
    while IFS= read -r row; do
        [ -n "$row" ] || continue
        fr="$(awk -F'|' '{gsub(/ /, "", $2); print $2}' <<<"$row")"
        tasks="$(awk -F'|' '{print $5}' <<<"$row")"
        status="$(awk -F'|' '{gsub(/^[[:space:]]+|[[:space:]]+$/, "", $8); print $8}' <<<"$row")"

        if ! grep -qw -- "$status" <<<"$LEGAL_STATUSES"; then
            drift+=("$matrix: $fr has status '$status' (expected one of: $LEGAL_STATUSES)")
            continue
        fi

        [ "$status" = "NOT-IMPLEMENTED" ] || continue

        if [ ! -f "$ledger" ]; then
            drift+=("$matrix: $fr is NOT-IMPLEMENTED but $ledger does not exist to carry its task")
            continue
        fi

        open=0
        for id in $(grep -oE 'T[0-9]{3}' <<<"$tasks" | sort -u); do
            grep -qE "^- \[ \] $id " "$ledger" && open=1
        done
        [ "$open" -eq 1 ] ||
            drift+=("$matrix: $fr is NOT-IMPLEMENTED with no open task in $ledger — undelivered and unowned")
    done < <(grep '^| FR-' "$matrix")

    # --- D) every cited path must be on disk --------------------------------
    checked=0
    while read -r cited; do
        [ -n "$cited" ] || continue
        checked=$((checked + 1))
        [ -e "$cited" ] && continue
        is_exempt "$cited" && continue
        drift+=("$matrix: cites '$cited', which is not on disk")
    done < <(
        grep -oE '`(backend|frontend|specs|docker|scripts|docs)/[^`[:space:]]+`' "$matrix" |
            tr -d '`' | sed -E 's/(::|#).*$//' | sort -u
    )
    printf '   %-32s %2d unique cited paths\n' "citations on disk" "$checked"
done

if [ ${#drift[@]} -gt 0 ]; then
    echo
    echo "TRACEABILITY VIOLATIONS:"
    for message in "${drift[@]}"; do
        echo "  ✗ $message"
    done
    echo
    echo "Update the feature's traceability.md in the same change set as the work."
    echo "Never record a status you have not verified: a matrix that lies is worse"
    echo "than no matrix."
    exit 1
fi

echo
echo "Traceability OK: every ratified requirement has an honest row and every citation is real."
