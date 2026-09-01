#!/usr/bin/env bash
#
# Task-ledger integrity gate: `specs/*/tasks.md` is the project's evidence that
# work was done, so every claim it makes must be mechanically verifiable.
#
# AGENTS.md turns each task into one atomic commit prefixed with its task id,
# and every progress query greps for that id. A ledger that carries a malformed
# id, claims a file that is not on disk, or issues the same id twice is not
# evidence — it is prose. This gate rejects all three.
#
# Checks, for every `specs/*/tasks.md`:
#   A) every task id matches ^T[0-9]{3}$
#      (commit 211c3e1 once renamed T056-T063 to X056-X063 while flipping them
#      to [X] in the same diff, hiding user story US3 from every id-based grep)
#   B) every task marked [x]/[X] cites only paths that exist on disk
#      (the same commit deleted a feature file whose task stayed complete)
#   C) no task id appears twice
#
# It also prints, per phase, how many tasks are open versus complete.
#
# A "cited path" is a backticked, repo-relative path under one of the known
# top-level directories. Backticked prose that is not a path (`composer
# test:unit`, `[P]`, class and method names) is ignored by construction.
#
# Documented intentional exceptions (see SUPERSEDED_PATHS below):
#   - backend/src/Rulesets/Infrastructure/Persistence/InMemoryStageOccupancyChecker.php
#     T036 shipped this as an explicit stub and T047 replaced it with the real
#     Doctrine adapter, exactly as T036's own text says it would. The task was
#     delivered; the artifact was superseded by design, not lost.
#
# Requirements: bash, grep, coreutils. No network, no stack, no toolchain.
#
# Usage: scripts/check-task-integrity.sh   # 0 = clean, 1 = drift, 2 = failure

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || {
    echo "check-task-integrity: cannot enter repository root." >&2
    exit 2
}

# Paths a completed task may legitimately cite even though they are gone.
# Add an entry only with a one-line rationale in the header above.
SUPERSEDED_PATHS=(
    "backend/src/Rulesets/Infrastructure/Persistence/InMemoryStageOccupancyChecker.php"
)

is_superseded() {
    local candidate="$1" known
    for known in "${SUPERSEDED_PATHS[@]}"; do
        [ "$candidate" = "$known" ] && return 0
    done
    return 1
}

shopt -s nullglob
LEDGERS=(specs/*/tasks.md)
shopt -u nullglob

if [ ${#LEDGERS[@]} -eq 0 ]; then
    echo "check-task-integrity: no specs/*/tasks.md found." >&2
    exit 2
fi

drift=()

for ledger in "${LEDGERS[@]}"; do
    echo "== $ledger"

    seen_ids=""
    phase=""
    phase_open=0
    phase_done=0
    total_open=0
    total_done=0

    flush_phase() {
        [ -n "$phase" ] || return 0
        printf '   %-58s %2d complete / %2d open\n' "$phase" "$phase_done" "$phase_open"
    }

    while IFS= read -r line; do
        # Phase heading: close the running tally and start a new one.
        if [[ "$line" =~ ^##[[:space:]]+(Phase[[:space:]].*)$ ]]; then
            flush_phase
            phase="${BASH_REMATCH[1]}"
            phase_open=0
            phase_done=0
            continue
        fi

        # Task line: "- [ ] T001 ..." or "- [x] T001 ..."
        [[ "$line" =~ ^-[[:space:]]\[([xX[:space:]])\][[:space:]]+([^[:space:]]+) ]] || continue
        mark="${BASH_REMATCH[1]}"
        id="${BASH_REMATCH[2]}"

        if [ "$mark" = " " ]; then
            phase_open=$((phase_open + 1))
            total_open=$((total_open + 1))
            complete=0
        else
            phase_done=$((phase_done + 1))
            total_done=$((total_done + 1))
            complete=1
        fi

        # A) id shape
        if ! [[ "$id" =~ ^T[0-9]{3}$ ]]; then
            drift+=("$ledger: malformed task id '$id' (expected T000 form)")
        fi

        # C) uniqueness
        if [[ " $seen_ids " == *" $id "* ]]; then
            drift+=("$ledger: duplicate task id '$id'")
        else
            seen_ids="$seen_ids $id"
        fi

        # B) cited paths of completed tasks must exist
        [ "$complete" -eq 1 ] || continue
        while read -r cited; do
            [ -n "$cited" ] || continue
            [ -e "$cited" ] && continue
            is_superseded "$cited" && continue
            drift+=("$ledger: $id is complete but '$cited' does not exist")
        done < <(
            grep -oE '`(backend|frontend|specs|docker|scripts|docs)/[A-Za-z0-9_./-]+`' \
                <<<"$line" | tr -d '`' | sort -u
        )
    done < "$ledger"

    flush_phase
    printf '   %-58s %2d complete / %2d open\n' "TOTAL" "$total_done" "$total_open"
done

if [ ${#drift[@]} -gt 0 ]; then
    echo
    echo "TASK LEDGER INTEGRITY VIOLATIONS:"
    for message in "${drift[@]}"; do
        echo "  ✗ $message"
    done
    echo
    echo "Repair the ledger in the same change set as the work it describes."
    echo "Never mark a task complete whose deliverable is not on disk."
    exit 1
fi

echo
echo "Task ledger OK: ids well-formed and unique, every completed task's files exist."
