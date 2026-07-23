---
name: review
description: Run a lead code review on the current branch or a given PR/MR. Standalone entry point for the lead-reviewer agent.
argument-hint: [PR-number-or-URL]
---

# Review

Standalone code review for any PR/MR. Runs the full lead-reviewer analysis against the spec
and project standards. Posting inline comments and the summary is your choice — you are
prompted at the end. Platform commands are shown for GitHub (`gh`) and GitLab (`glab`); "PR"
means PR or MR throughout.

## Step 1 — Config

`owner/repo` and the platform are auto-derived from `git remote get-url origin` (an AGENTS.md
value overrides). Specs live at
`.TemporaryItems/Issues/<repo>/issues/<N>-spec.md`.

## Step 2 — Resolve the PR

If `$ARGUMENTS` is provided, use it as the PR number or URL.

Otherwise resolve from the current branch:
```bash
# GitHub:
gh pr list --head "$(git branch --show-current)" --json number,url -q '.[0] | "\(.number) \(.url)"'
# GitLab:
glab mr list --source-branch "$(git branch --show-current)" -F json | jq -r '.[0] | "\(.iid) \(.web_url)"'
```

If no PR is found, tell the user and stop.

Get the base branch and body from the PR (GitHub:
`gh pr view <PR_NUMBER> --json baseRefName,body`; GitLab:
`glab mr view <PR_NUMBER> -F json | jq '{base: .target_branch, body: .description}'`).

Extract the linked issue number from the PR body (`Fixes #N`, `Closes #N`, or an issue URL).

## Step 3 — Locate the spec

If a linked issue number was found, check for a spec at:
`.TemporaryItems/Issues/<repo>/issues/<N>-spec.md`

If it exists, pass its path to the agent.
If it does not exist, inform the user: "No grooming spec found — the review will check
against project standards only (spec compliance section will be skipped)."

## Step 4 — Invoke the lead-reviewer agent

Invoke the `lead-reviewer` sub-agent with:
- Issue number (if known) and spec path (if found, else omit)
- Base branch from Step 2
- PR number
- `CURRENT_MODEL`: "standalone"
- `session_learnings`: read the project's agent-guidelines file if it exists, else pass empty string
- Repo: `<owner>/<repo>`

> **STANDALONE MODE** — two differences from the normal pipeline run:
> 1. **Skip Step 5 (inline PR comments) and Step 5b (summary PR comment).** Instead, output
>    the full review report — findings table, blockers, nice-to-haves — as formatted Markdown
>    in your response, in a section titled `## Review Report`.
> 2. **Skip the StructuredOutput JSON return.** Return a short human-readable verdict summary
>    instead: overall verdict, blocker count, and any open questions.

## Step 5 — Offer to post

After the agent responds, display its `## Review Report` and ask:

> **Post this review to PR #\<PR_NUMBER\>?**
> Reply `yes` to post inline comments + summary, `no` to finish here.

**If yes** — the agent posts inline comments (Step 5) and the summary comment (Step 5b)
using the normal dedup flow. The `<!-- ai-pipeline:lead-review -->` marker ensures a
later pipeline re-run updates in place rather than duplicating.

**If no** — confirm the review is complete and finish.
