---
name: challenge
description: Adversarially review a grooming spec before implementation starts. Finds hidden risks, unvalidated assumptions, and missing dependencies. Standalone entry point for the challenger agent.
argument-hint: <issue-number>
---

# Challenge

Standalone adversarial spec review. Runs the full challenger analysis on an existing
grooming spec and outputs a verdict (APPROVED / NEEDS_REVISION / BLOCKED) with
MoSCoW-classified findings. Posting to the issue is your choice — you are prompted at the
end. Platform commands are shown for GitHub (`gh`) and GitLab (`glab`).

## Step 1 — Locate files

`owner/repo` and the platform are auto-derived from `git remote get-url origin` (an AGENTS.md
value overrides). Use `$ARGUMENTS` as the issue number
`N`. Verify both files exist:
- Issue file: `.TemporaryItems/Issues/<repo>/issues/<N>.md`
- Spec file: `.TemporaryItems/Issues/<repo>/issues/<N>-spec.md`

If the spec does not exist, tell the user: "No spec found for issue #N. Run `/groom <N>`
first to produce one." Stop here.

If only the issue file is missing, sync it:
```bash
bash .claude/skills/orchestrator/scripts/issue-sync.sh <N>
```

## Step 2 — Invoke the challenger agent

Invoke the `challenger` sub-agent with:
- Issue number `N`
- Issue file path: `.TemporaryItems/Issues/<repo>/issues/<N>.md`
- Spec file path: `.TemporaryItems/Issues/<repo>/issues/<N>-spec.md`
- `plan_version`: 1 (or detect from the spec's `Plan v<N>` header if present)
- `CURRENT_MODEL`: "standalone"
- `session_learnings`: read the project's agent-guidelines file if it exists, else pass empty string
- Repo: `<owner>/<repo>`

> **STANDALONE MODE** — one difference from the normal pipeline run:
> **Skip the StructuredOutput JSON return.** Output the full human-readable verdict
> (APPROVED / NEEDS_REVISION / BLOCKED) and findings in a section titled
> `## Challenge Report`, using the same format the orchestrator would receive
> (verdict, MoSCoW-classified findings, alternative suggestions).

## Step 3 — Offer to post

After the agent responds, display its `## Challenge Report` and ask:

> **Post this challenge report as a comment on issue #\<N\>?**
> Reply `yes` to post, `no` to finish here.

**If yes** — post with dedup (body always starts with `<!-- ai-pipeline:challenge -->`):

GitHub:
```bash
EXISTING_ID=$(gh api repos/<owner>/<repo>/issues/<N>/comments \
  --jq '[.[] | select(.body | contains("<!-- ai-pipeline:challenge -->"))] | last | .id // empty')
# update: gh api --method PATCH repos/<owner>/<repo>/issues/comments/$EXISTING_ID -f body=...
# new:    gh issue comment <N> --body "..."
```
GitLab: find the note containing the marker via `glab api projects/:id/issues/<N>/notes`, then
update it or add a new one with `glab issue note <N> -m "..."`.

**If no** — finish. Remind the user: if the verdict is NEEDS_REVISION, update the spec at
`.TemporaryItems/Issues/<repo>/issues/<N>-spec.md` before running `/groom <N>` again or starting implementation.
