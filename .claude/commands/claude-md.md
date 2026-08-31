---
description: Write CLAUDE.md so the project's rules are auto-loaded
---

Read `docs/prompts/09-claude-md.md` in full and execute it exactly as written.

That file is a complete, standalone brief: it carries its own context, preconditions, the
diagnosis of the problem, numbered instructions, explicit scope constraints, runnable acceptance
criteria, and the branch and commit conventions to follow. Treat every section as binding —
especially `<constraints>` and `<completion>`.

Do not begin editing before completing the file's first instruction, which is always to confirm
that its diagnosis still holds against the current code. If it does not, stop and report that
rather than applying a fix for a problem that has moved.
