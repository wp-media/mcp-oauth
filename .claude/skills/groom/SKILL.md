---
name: groom
description: Groom a single issue — produce an implementation spec and optionally post the grooming summary as an issue comment. Standalone entry point for the grooming-agent.
argument-hint: <issue-number>
---

# Groom

Standalone grooming for a single issue. Runs the full grooming analysis and writes the spec
to disk. Posting to the issue tracker is your choice — you are prompted at the end. Platform
commands are shown for GitHub (`gh`) and GitLab (`glab`); keep the one your project uses.

## Step 1 — Config

`owner/repo` and the platform are auto-derived from `git remote get-url origin` (an AGENTS.md →
Project Configuration value overrides). Issue and spec files live at:
- Issue file: `.TemporaryItems/Issues/<repo>/issues/<N>.md`
- Spec file: `.TemporaryItems/Issues/<repo>/issues/<N>-spec.md`

## Step 2 — Sync the issue

Ensure the issue file exists at `.TemporaryItems/Issues/<repo>/issues/<N>.md`. Run:

```bash
bash .claude/skills/orchestrator/scripts/issue-sync.sh <N>
```

If the file already exists and is recent (less than 5 minutes old), skip the sync.

## Step 3 — Invoke the grooming agent

Invoke the `grooming-agent` sub-agent with the following invocation context:

> Issue number: `<N>`
> Issue file path: `.TemporaryItems/Issues/<repo>/issues/<N>.md`
> Repo: `<owner>/<repo>`
> complexity_signal: derive from the issue content yourself
>
> **STANDALONE MODE** — two differences from the normal pipeline run:
> 1. **Skip Step 5 (posting to the issue).** Instead, include the full comment body you would
>    have posted in a section titled `## Grooming Comment Draft` at the end of your response.
>    Use exactly the same format the pipeline would post (including the
>    `<!-- ai-pipeline:grooming-plan -->` marker), so it is ready to post as-is.
> 2. **Skip the StructuredOutput JSON return.** Return a short human-readable summary instead:
>    effort, risk, complexity, and any open questions.

The spec file is still written to `.TemporaryItems/Issues/<repo>/issues/<N>-spec.md` as normal.

## Step 4 — Offer to post

After the agent responds, display its `## Grooming Comment Draft` to the user, then ask:

> **Post this as a comment on issue #\<N\>?**
> Reply `yes` to post as-is, `no` to finish without posting, or paste edited text to post a
> modified version.

**If yes** — check for an existing grooming comment first (dedup), then update it in place if
found, otherwise post a new comment.

GitHub:
```bash
EXISTING_ID=$(gh api repos/<owner>/<repo>/issues/<N>/comments \
  --jq '[.[] | select(.body | contains("<!-- ai-pipeline:grooming-plan -->"))] | last | .id // empty')
# update: gh api --method PATCH repos/<owner>/<repo>/issues/comments/$EXISTING_ID -f body=...
# new:    gh issue comment <N> --body "..."
```
GitLab: find the note containing the marker via `glab api projects/:id/issues/<N>/notes`, then
update it (`PUT .../notes/:note_id`) or add a new one with `glab issue note <N> -m "..."`.

**If no** — confirm the spec was written to `.TemporaryItems/Issues/<repo>/issues/<N>-spec.md` and finish.

**If edited** — use the user-provided text as the comment body and post.
