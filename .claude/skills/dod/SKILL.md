---
name: dod
description: >
  Run the Definition of Done checklist for the current branch and report PASS/WARN/FAIL with
  evidence. Works in the delivery pipeline (layer 1 self-check inside the implementer; layer 2
  independent gate from the orchestrator) and as a standalone skill a user can invoke any time to
  check the definition of done for the current branch or an open PR/MR. Pass layer: "1" or "2" inside the pipeline; omit it
  when invoking manually. Use to ensure a branch/PR/MR is ready to be shared and reviewed.
---

> **Configuration.** The command `<placeholders>` in this file (`<test command>`, `<lint command>`,
> `<static-analysis command>`, `<lint auto-fix command>`) are project-specific and defined in
> **AGENTS.md → Project Configuration**. Read that section and use the real commands; if one is not
> defined, mark the dependent check `N/A` with a reason rather than guessing. The repo, platform
> (`gh` vs `glab`), and base branch are auto-derived from git — see Step 0. "PR" means PR or MR.

# DOD SKILL

You are a quality gate checker. Run all Definition of Done checks for the current branch and report
the results as a structured JSON object plus a short human-readable summary.

## Invocation contexts

The skill runs in one of three contexts. They share the same six checks; they differ only in **who
invokes it**, **whether a PR/MR exists yet**, and **whether the caller will self-correct FAILs**.

| Context | `layer` | PR/MR open? | Behavior |
|---|---|---|---|
| **Self-check** (inside the implementer, step 3) | `"1"` | No — pre-push | Reads the local PR draft and runs CI commands locally. The agent self-corrects any `FAIL` and re-runs; `overall` must be `PASS` or `WARN` on handoff. |
| **Independent gate** (orchestrator, after handoff) | `"2"` | Yes | Fresh, unbiased read. Can return `FAIL`. Populates `layer1_delta` — issues this pass caught that the self-check missed. |
| **Standalone** (a user runs it directly) | omitted | Maybe | Same independent read as the gate. Can return `FAIL`. No self-correction loop — report findings and let the user act. Discovers context itself (see below); skips checks whose inputs are unavailable, marking them `N/A` with a reason. |

**Detecting the context:** if a `layer` was passed, you are in the pipeline — follow that layer's
rules. If no `layer` was passed, you are **standalone**: behave like the independent gate, but
establish the PR/branch yourself in **Step 0** below, and never assume an orchestrator-provided
`file_scope` or PR draft exists.

Throughout the checks, the practical distinction is **"is there an open PR/MR?"** — that decides
whether to read a local draft + run CI locally (no PR) or fetch the PR body + read remote CI
(PR open). The `layer` value just tells you which caller you're serving and whether FAIL is allowed.

---

## Step 0 — Establish context (standalone only)

If a `layer` was passed, skip this — the caller already provided `base_branch`, `file_scope`, and
(for layer 2) `pr_url`. Otherwise determine:

0. **Repo & platform** — derive `owner/repo` and the platform from `git remote get-url origin`
   (host `github.com` → `gh`; a GitLab host → `glab`). An AGENTS.md → Project Configuration value
   overrides. This selects which command pairs below to use.

1. **Is there an open PR/MR for the current branch?**
   ```bash
   # GitHub:
   PR_URL=$(gh pr list --head "$(git branch --show-current)" --json url -q '.[0].url // empty')
   # GitLab:
   # PR_URL=$(glab mr list --source-branch "$(git branch --show-current)" -F json | jq -r '.[0].web_url // empty')
   ```
   If found, treat it like the independent gate (fetch the PR body, read remote CI). If not, run
   like the self-check's local path (no PR draft to read — Check 1 and Check 4 become `N/A` unless
   the user points you at a draft).

2. **Base branch** — resolve from the PR if there is one, else default to the project's default
   branch (see Base branch guard).

3. **File scope** — there is no orchestrator-provided `file_scope`. Check 6 is `N/A` in standalone
   unless the user explicitly supplies an in-scope file list.

---

## Inputs

| Parameter | Type | Description |
|---|---|---|
| `layer` | `"1"`, `"2"`, or omitted | `"1"` = self-check inside the implementer; `"2"` = independent gate from the orchestrator; omitted = standalone user invocation (behaves like the gate). |
| `file_scope` | array of file paths (optional) | Files declared in-scope (orchestrator passes this for layer 1). Used by Check 6. `N/A` when not provided. |
| `base_branch` | string (optional) | The PR base branch. Defaults to the project's default branch. Used in all `git diff` commands. |
| `pr_url` | string (optional) | The PR/MR URL. Used by Check 1, 4, 5 whenever a PR/MR exists (layer 2 and standalone-with-PR). |

---

## Anti-rationalization table

Before running the checks, acknowledge these. Agents are good at producing plausible reasons to skip steps — this table preempts them.

| You'll be tempted to say | Why you can't |
|---|---|
| "The change is too small to need a test" | Acceptance criteria still apply. A one-line fix to a function still needs a test on that function. |
| "Tests pass, so the gate is fine" | Passing tests are evidence, not proof. The self-check self-reports; an independent pass is the real read. |
| "No public API change, skipping docs" | Check for new public functions, endpoints, CLI commands, config keys, or events. Those count as public API. |
| "I'll skip e2e because the environment might not boot" | Boot it. If it fails, `SKIP` is a valid status — but you must attempt it first. |
| "The PR description section is present" | Present is not the same as filled. Thin is a WARN — name it explicitly. |
| "I'll add tests in a follow-up ticket" | "Later" is the load-bearing word. There is no later. See Check 2. |

---

## Base branch guard

Before running any check, determine the PR base branch. All `git diff` commands below assume the project's default branch (`main` in this repo — see `AGENTS.md` › Repository & branches), but if the PR targets a different base this silently compares the wrong tree.

```bash
# GitHub:
BASE=$(gh pr view "$PR_URL" --json baseRefName --jq .baseRefName 2>/dev/null)
# GitLab: BASE=$(glab mr view "$PR_URL" -F json | jq -r .target_branch)
if [ "$BASE" != "main" ]; then
  echo "WARNING: Base branch is '$BASE', not the default. Adjust git diff commands accordingly."
fi
```

Use `origin/$BASE` in place of `origin/main` in every diff command throughout this skill. If `$BASE` is empty (no PR yet, or none found in standalone), default to `main` (do **not** trust `git symbolic-ref origin/HEAD` — a stale clone reports `master`; see `AGENTS.md`).

---

## The 6 checks

Run each check in order. Report **PASS**, **WARN**, or **FAIL** with specific evidence for each.

---

### Check 1 — Manual validation confirmed

Look at the PR description:
- **No PR open yet** (self-check, or standalone pre-PR): read the local draft at `.TemporaryItems/Issues/<repo>/pull/<N>.md` if one exists.
- **PR open** (gate, or standalone with a PR): fetch it (GitHub: `gh pr view <PR_NUMBER> --json body -q .body`; GitLab: `glab mr view <PR_NUMBER>`).
- **Standalone with neither** a PR nor a local draft: mark this check `N/A` (there is no description to validate yet).

Look at the "What was tested" section. It must contain **concrete scenarios** — not "N/A", not "tested locally".

**The acceptance criteria are the yardstick.** Read the AC from the ticket/spec (or the issue the
PR closes) and confirm the manual validation actually covers them: every AC that involves
observable behavior should map to a described scenario and its outcome. The AC define *what must
have been tested* — a "What was tested" section that skips an AC is incomplete, however detailed it
is about everything else. If no AC are available, fall back to the issue's expected-behavior
description.

- **PASS**: Every behavioral AC maps to a described manual scenario and its outcome
- **WARN**: Section is present but thin — covers some AC but not all, or only one scenario for a complex change. Name the uncovered AC.
- **FAIL**: Section is empty or says "N/A" without justification when a PR/MR is open. Before any PR exists, a missing draft is not a FAIL — report `N/A`.

---

### Check 2 — Automated tests in place

Identify changed source files:
```bash
git diff origin/$BASE --name-only
```

For each changed source file, check that a corresponding test exists. Test files mirror the
source structure following the project's convention (e.g. `src/foo/Bar.<ext>` →
`tests/foo/Bar.test.<ext>` or similar).

**The acceptance criteria scope the coverage.** The same AC used in Check 1 are also a baseline of what the
automated tests should lock in: each AC that is testable in code should have a test asserting it,
so the behavior can't silently regress later. Check both directions — changed files have tests,
*and* each behavioral AC is covered by at least one test (automated where feasible; if an AC was
only validated manually in Check 1, say so and treat it as a coverage gap, not a pass).

Then run the test suite, scoped to the changed area where possible:
```bash
<test command> <targeted-filter-or-group>   # the subset covering the changed files
```
Run only the relevant subset, not the full suite, unless the change is high-risk.

- **PASS**: All changed source files have tests, every testable AC is covered, AND tests pass
- **WARN**: A changed file or a behavioral AC has no corresponding test. When reporting this, you MUST include an explicit written statement in `evidence`: the filename or AC, the reason a test does not exist (not "too small" or "follow-up ticket" — those are rationalizations), and whether the missing test represents a real gap. "Later" is the load-bearing word — there is no later. If the only honest reason is "I didn't write it", that is a FAIL, not a WARN.
- **FAIL**: Tests fail or error out, OR the agent's stated reason for a missing test is "I'll do it in a follow-up"

---

### Check 3 — Documentation updated

Run `git diff origin/$BASE --name-only` and look for changes to the public API surface:
- New or changed public functions, methods, or exported types
- New or changed HTTP endpoints, RPC methods, or events emitted/consumed
- New or changed CLI commands
- New or changed configuration keys, persisted option names, or permissions
- New or changed package/plugin metadata

If the project has a docs directory (e.g. `docs/`), check whether the matching doc was updated.
If it has none, evaluate the diff itself: if it introduces new public API surface, note that
documentation must be updated and mark **WARN** — without requiring any specific file to have
changed.

- **PASS**: No new public API surface introduced, or the change is internal-only
- **WARN**: The diff introduces new public API surface — note that documentation must be updated for it
- **FAIL**: Multiple new public-facing API additions with no acknowledgement that documentation is required

---

### Check 4 — PR description matches template

Read the repo's PR template:
```bash
cat .claude/skills/orchestrator/refs/pr-template.md
```

Then fetch the PR body:
- **No PR open yet:** read the local draft `.TemporaryItems/Issues/<repo>/pull/<N>.md` if it exists.
- **PR open:** GitHub `gh pr view <PR_NUMBER> --json body -q .body` / GitLab `glab mr view <PR_NUMBER>`.
- **Standalone with no PR and no draft:** mark this check `N/A`.

Check that all required sections from the template are present and non-empty (the built-in
template's sections; adapt if the project has its own template):
- Description (with `Closes #N` / `Fixes #N`)
- Type of change (one checkbox ticked)
- What was tested
- How to test
- Affected Features & Quality Assurance Scope
- Technical description
- New dependencies
- Risks
- Mandatory checklist items

- **PASS**: All required sections present and filled
- **WARN**: One section is thin or partially filled
- **FAIL**: 2+ sections missing / left with placeholder text. (When no PR/MR is open and no draft exists, this check is `N/A`, not FAIL.)

---

### Check 5 — CI passes

**No PR open yet (self-check, or standalone pre-PR) — run the project's CI commands locally:**
```bash
<lint command>             # fast lint on changed files
<static-analysis command>  # type/static checks, if the project has them
<test command>             # unit suite (scoped where possible)
```

If the linter has an auto-fix mode, apply it then re-check — never fix file-by-file:
```bash
<lint auto-fix command>
<lint command>             # confirm 0 remaining violations
```

**PR open (gate, or standalone with a PR) — read remote CI status:**

First, read the CI workflow files to enumerate which checks are expected (GitHub:
`ls .github/workflows/`; GitLab: read `.gitlab-ci.yml`). Note the check/job names.

Wait for all checks to complete, then report any failures:
```bash
# GitHub — blocks until all checks complete:
gh pr checks "$PR_URL" --watch
gh pr checks "$PR_URL" --json name,state,link --jq '.[] | select(.state == "FAILURE") | {name, link}'
# GitLab equivalent:
#   glab ci status        # watch the pipeline for this MR's branch
#   glab ci view          # inspect failing jobs
```

For any failing check, fetch the relevant error excerpt (GitHub:
`gh run view <run_id> --log-failed | tail -30`; GitLab: `glab ci trace <job-id> | tail -30`).

Include each failure as a separate blocker in the return JSON with:
- `check`: the check/job name
- `error_excerpt`: the relevant log lines
- `suggested_fix`: one sentence on what likely caused it

Also verify the `Co-Authored-By: <model> <noreply@anthropic.com>` trailer is present on every
pipeline-authored commit on the branch:
```bash
git log <base_branch>..HEAD --format="%H %s" | while read sha msg; do
  git show $sha --format="%b" -s | grep -qE "Co-Authored-By: .+ <noreply@anthropic.com>" \
    || echo "MISSING Co-Authored-By on $sha"
done
```

- **PASS**: All checks green AND trailer present on every commit
- **WARN**: A non-blocking check (e.g. coverage threshold) is failing
- **FAIL**: Any required check is failing, or any commit is missing the trailer

---

### Check 6 — File scope compliance

**Only runs when a `file_scope` was provided** — the orchestrator passes it for the self-check
(layer 1). In the independent gate (layer 2) and in standalone runs, no scope is tracked, so this
check is `N/A`.

When provided, `file_scope` (array of paths) is compared against `git diff <base_branch>..HEAD --name-only`.

List every file changed on the branch:
```bash
git diff <base_branch>..HEAD --name-only
```

Compare against the `file_scope` input. Flag any file that appears in the diff but not in `file_scope`.

Exceptions that do not count as violations:
- Auto-generated files (minified bundles, lock files, generated code)
- Test files that directly correspond to a changed source file (mirrored test files)
- Files the orchestrator explicitly added to scope via a `blocked_reason` note
- Files modified solely by the linter's auto-fixer when it has no "changed files only" mode and may reformat files outside scope. Note which files were auto-fixed and exclude them from the scope-violation count.

- **PASS**: All modified files are within declared scope (or no scope was declared) or have a justification.
- **WARN**: One or more files outside scope were modified without explanation
- **FAIL**: Two or more files outside scope were modified without explanation

**How a Check 6 FAIL rolls up:**
- **Self-check (layer 1):** reported as WARN in the overall verdict — handoff proceeds with a note. The self-check overall verdict is only ever PASS or WARN, never FAIL.
- **Independent gate (layer 2) / standalone:** a genuine FAIL that blocks the gate.

---

## Output format constraints

Apply these constraints strictly in every context (self-check, gate, and standalone):

**Length targets:**
- Total report: aim for ≤ 400 words (excluding JSON). If you exceed this, cut PASS summaries first.
- `evidence` field: one sentence maximum per check. State the finding, not the process ("3 unit tests cover the changed method" not "I ran the test command and reviewed the output and found that there are three test cases…").
- Do NOT repeat the check criteria in the evidence — the reader knows the criteria.

**What to omit:**
- PASS checks with no nuance: "tests pass" → replace with a one-line table row.
- Commands you ran: never narrate "I ran the lint command and saw…" — state only what you found.
- Justifications for doing the check: skip the preamble, go straight to the verdict.

**Condensed PASS format:** For checks that simply pass with no nuance, use a one-liner in a summary table instead of a prose paragraph:

| Check | Result | Note |
|---|---|---|
| 1. Manual validation | ✅ PASS | All 3 AC covered by described scenarios |
| 3. Docs | ✅ PASS | No public API change |
| 4. PR description | ✅ PASS | All sections filled |

Reserve prose evidence for: WARN, FAIL, and PASS-with-caveats checks only.

**What must always appear:**
- The overall verdict (PASS / WARN / FAIL) as the first line
- Any WARN or FAIL check with its evidence and a concrete remediation step
- The JSON result block (required for pipeline integration; harmless and still useful standalone)

```
| Check | Status | Evidence |
|-------|--------|----------|
| 1. Manual validation  | PASS | "What was tested" covers 3 concrete scenarios |
| 2. Automated tests    | WARN | src/cache/Purge has no test file |
| 3. Documentation      | PASS | docs/api.md updated |
| 4. PR description     | PASS | All sections filled |
| 5. CI                 | FAIL | static analysis failing: unchecked return value in src/cache/Purge:142 |
| 6. File scope         | PASS | All 4 changed files within declared scope |

Overall: FAIL

Blockers:
- Check 5: static analysis failing on src/cache/Purge:142 — handle the unchecked return value

Warnings (non-blocking):
- Check 2: src/cache/Purge has no test — consider filing a ticket
```

If all checks pass: print **PASS** clearly.
If any check fails: print **FAIL** and list each blocker with a suggested fix.

---

## Structured return object

Always return this JSON object in addition to the human-readable output above:

```json
{
  "overall": "PASS|WARN|FAIL",
  "checks": [
    { "name": "manual-validation", "status": "PASS|WARN|FAIL", "evidence": "string" },
    { "name": "automated-tests", "status": "PASS|WARN|FAIL", "evidence": "string" },
    { "name": "documentation", "status": "PASS|WARN|FAIL", "evidence": "string" },
    { "name": "pr-description", "status": "PASS|WARN|FAIL", "evidence": "string" },
    { "name": "ci", "status": "PASS|WARN|FAIL", "evidence": "string" },
    { "name": "file-scope", "status": "PASS|WARN|FAIL|N/A", "evidence": "string" }
  ],
  "blockers": [
    {
      "check": "ci|manual-validation|pr-description",
      "description": "Check 5: static analysis failing — unchecked return value in src/cache/Purge:142",
      "error_excerpt": "relevant log lines for CI failures — empty string for non-CI blockers",
      "suggested_fix": "handle the unchecked return value — empty string if unknown"
    }
  ],
  "warnings": ["Check 2: src/cache/Purge has no test file"],
  "layer1_delta": ["Issues this independent pass caught that the self-check missed — gate (layer 2) only; empty array otherwise"]
}
```

**Verdict rules by context:**
- **Self-check (layer 1):** `overall` must be `PASS` or `WARN` when the implementer hands off — FAILs are self-corrected first. Leave `layer1_delta` empty.
- **Independent gate (layer 2):** `overall` can be `PASS`, `WARN`, or `FAIL`. Populate `layer1_delta` with issues the self-check missed.
- **Standalone:** `overall` can be `PASS`, `WARN`, or `FAIL` — report it and stop; there is no self-correction loop and no prior pass to diff against, so leave `layer1_delta` empty.


---

## Project-specific notes

- Base branch defaults to the project's default branch. In the pipeline the orchestrator passes the right base; standalone, resolve it from the open PR/MR (Step 0) or fall back to the default branch.
- Wire the project's real test / lint / static-analysis commands into the `<...>` placeholders above. If the project enforces custom static-analysis rules, they run as part of the `<static-analysis command>`.
- The "public API surface" for Check 3 is whatever your project treats as public contract — extend the list to match (e.g. if an architecture skill defines additional surfaces).
- The `Co-Authored-By` trailer uses the model-versioned form: `<MODEL> <noreply@anthropic.com>`. Match exactly.

