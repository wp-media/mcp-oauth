---
name: qa
description: Run QA validation on a pull/merge request — boots the local environment, tests acceptance criteria, and optionally posts the report as a PR/MR comment. Standalone entry point for the qa-engineer agent.
argument-hint: <PR-number-or-URL>
---

# QA

Standalone QA run for any PR/MR. Boots the local environment, validates every acceptance
criterion, and produces a test report. Posting to the tracker is your choice — you are prompted
at the end. Platform commands are shown for GitHub (`gh`) and GitLab (`glab`); "PR" means PR or MR.

## Step 1 — Config

`owner/repo` and the platform are auto-derived from `git remote get-url origin` (an AGENTS.md
value overrides). Specs live at
`.TemporaryItems/Issues/<repo>/issues/<N>-spec.md`.

## Step 2 — Resolve the PR

Use `$ARGUMENTS` as the PR number or URL. If empty, resolve from the current branch:

```bash
# GitHub:
gh pr list --head "$(git branch --show-current)" --json number,url -q '.[0] | "\(.number) \(.url)"'
# GitLab:
glab mr list --source-branch "$(git branch --show-current)" -F json | jq -r '.[0] | "\(.iid) \(.web_url)"'
```

If no PR is found, tell the user and stop.

Get the base branch (GitHub: `gh pr view <PR_NUMBER> --json baseRefName -q .baseRefName`;
GitLab: `glab mr view <PR_NUMBER> -F json | jq -r .target_branch`).

## Step 3 — Invoke the qa-engineer agent

Invoke the `qa-engineer` sub-agent with:
- PR number and PR URL
- Base branch from Step 2
- Repo: `<owner>/<repo>`

> **STANDALONE MODE** — two differences from the normal pipeline run:
> 1. **Skip the PR-comment posting step.** Instead, output the full QA report as
>    formatted Markdown in your response, in a section titled `## QA Report`. Use the same
>    format the pipeline would post (including the `<!-- ai-pipeline:qa-report -->` marker).
> 2. **Skip the StructuredOutput JSON return.** Output a short human-readable summary
>    instead: overall result, pass/fail per criterion, and any blockers.

All other steps run normally — the local environment is booted, acceptance
criteria are tested, and the full validation is performed.

## Step 4 — Offer to post

After the agent responds, display its `## QA Report` and ask:

> **Post this QA report to PR #\<PR_NUMBER\>?**
> Reply `yes` to post, `no` to finish here.

**If yes** — post with dedup: check for an existing `<!-- ai-pipeline:qa-report -->` comment and
update it in place if found, otherwise create a new one. GitHub:
`gh api --method PATCH repos/<owner>/<repo>/issues/comments/$EXISTING_ID` or `gh pr comment`.
GitLab: update the note via `PUT projects/:id/merge_requests/:iid/notes/:note_id` or `glab mr note`.

**If no** — confirm the QA run is complete and finish.
