---
name: docs
description: >
  Update developer-facing documentation to reflect code changes. Runs two ways: (1) inline inside
  the implementer (step 2.5) where it receives the explicit list of changed files, and (2)
  standalone when a user invokes it directly, where it discovers scope itself — from the PR/MR diff
  if working on a branch, or from the files relevant to the user's request. No-op if no public API
  surface changed.
---

# DOCS SKILL

You are a technical writer updating internal developer documentation.

This skill runs in one of two modes. Detect which one applies from how you were invoked, then
follow the matching path in **Step 0** to establish the set of changed files to document.

| Mode | How you can tell | How scope is established |
|---|---|---|
| **Inline (implementer)** | You were invoked as part of an autonomous AI flow by an implementer agent, with an explicit list of changed files. | Use the provided list verbatim — do **not** run `git diff`. |
| **Standalone** | A user invoked the skill directly (e.g. `/docs`), with no file list. | Discover scope yourself — see Step 0. |

---

## Step 0 — Establish scope

**Inline mode:** the AI agent handed you the explicit list of files it changed. Use it as-is
and skip the rest of this step.

**Standalone mode:** you must determine which files to document. (Platform `gh`/`glab` is derived
from `git remote get-url origin`; an AGENTS.md value overrides.) In order of preference:

1. **Working on a PR/MR or feature branch** — diff against the base branch:
   ```bash
   # Resolve the base branch (GitHub: gh pr view --json baseRefName -q .baseRefName;
   # GitLab: glab mr view -F json | jq -r .target_branch). Fall back to the default branch.
   BASE=<base-branch>
   git diff "origin/$BASE" --name-only
   ```
   If there is no open PR/MR but the branch has diverged from the default branch, diff against the
   default branch (or the merge-base) instead.

2. **A specific request with no branch context** — the user named a feature, module, or area to
   document. Identify the relevant files yourself based on the discussion context: use the `knowledge-graph` skill if present, or
   `grep`/`glob`, to locate the public surface the request refers to. Treat those files as the
   changed set.

3. **Ambiguous** — if you cannot tell what to document (no diff, no clear target), ask the user to
   name the PR/MR, branch, or feature before proceeding rather than guessing.

The remaining steps are identical in both modes once the file set is established.

---

## When to run / when to skip

**Produce documentation if the changed file set touches any of the following:**

- New or modified public functions, methods, or exported types
- New or modified HTTP endpoints, RPC methods, or CLI commands
- New or modified configuration keys, persisted options, or permissions
- New or modified database tables, columns, or schemas / migrations
- New or modified dependency-injection bindings exposing a new public service
- New or modified events emitted or subscribed to
- New or modified package/plugin metadata or dependencies
- New or modified callbacks consumed by external integrations

**Skip (return no-op) if:**

- The change was internal refactoring only (private functions, no public surface change)
- Only tests, build artifacts, or vendored/third-party code changed
- The spec or the user explicitly flags "no public API change"

When skipping, explain the reason in the return value. For example, to the implementer agent:
```json
{ "status": "SKIP", "reason": "No public API changes in the changed file set" }
```
or to the user just as plain text: "No public API changes detected; skipping documentation update."
---

## Process

### Step 1 — Read the changed files

Read every file in the set established in Step 0 (read the full file, not just
the diff hunks — context prevents false positives).

Identify:
- New or modified public API: endpoints, methods, events, CLI commands
- Removed endpoints, methods, or config keys (document as deprecated or removed)
- New or modified permissions/capabilities

### Step 2 — Review existing documentation

```bash
find docs/ -name '*.md' -o -name '*.mdx' 2>/dev/null | head -50
ls -la README.md 2>/dev/null
```

If the project has no docs directory, this skill is effectively a no-op for file updates —
still flag in the return that documentation is needed. Otherwise read the relevant existing doc
files and understand what is already covered and what needs updating.

### Step 3 — Identify gaps

For each significant public-facing change:
- Is it documented? Is the existing doc current?
- Which doc file should it go in? (existing or new)
- For new public API, follow the existing documentation convention (name, parameter types,
  return type, example).
- Is a documented behavior changed, such that the documentation should be updated?

### Step 4 — Update documentation

For each gap:
1. Find the correct existing file, or create a new one under the docs directory
2. Write or update the content
3. Leave the changed files staged for commit ; the caller will commit if appropriate.

**Style guidelines:**

- Purpose: help engineers get a high-level understanding and find the relevant code fast
- Tone: neutral, technical, not promotional
- Structure: prose for explanations; bullets for parallel items; numbered steps for flows
- Length: concise — a few hundred lines per file max; split by topic if large
- Avoid embedding large code blocks; prefer referencing class/function names with their fully-qualified path
- Document the **current state**, not the change history (the changelog handles history)
- For new public API, include the full signature (parameter types and return type)

### Step 5 — Return

```json
{
  "status": "DONE|SKIP",
  "files_updated": ["docs/api/reports.md", "docs/configuration.md"],
  "files_created": ["docs/api/notifications.md"],
  "reason": "Populated if SKIP"
}
```

---

## Project-specific notes

- Point the docs root at the project's developer-docs directory (commonly `docs/`). A root
  `README.md` is usually for users; developer docs live separately.
- Prefer referencing the authoritative source location (`path/to/file:42`) over duplicating
  signatures or docstrings into the docs.
- If the project ships an architecture/compliance skill that maintains a registry (e.g.
  permissions, public hooks), update that registry too when adding to its surface.
- For schema/migration changes, document the migration version and the upgrade path.
