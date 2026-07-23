#!/usr/bin/env bash
# Sync an issue into a local Markdown snapshot for review.
# Usage: issue-sync.sh <issue-number>
#
# Repo, platform, and repo-slug are AUTO-DERIVED from the git `origin` remote. Override any of
# them only when the defaults are wrong (e.g. a fork whose canonical repo is not `origin`):
#   REPO       — canonical "<owner>/<repo>"            (default: parsed from origin)
#   PLATFORM   — "github" or "gitlab"                  (default: detected from the origin host)
#   REPO_SLUG  — short name for the temp-file path     (default: the repo name)
#
# This is the GENERIC, deliberately small version. It fetches the issue (title, body,
# labels, assignees, comments) and writes a structured Markdown snapshot. It does NOT
# detect epics / sub-issues / project fields — that logic is platform-specific and varies
# by team. If you need it, add a platform-specific enhancement that appends an
# "## Epic Signals" / "## Sub-issues" section to the snapshot; the orchestrator and
# grooming-agent read those sections if present and degrade gracefully if absent.
set -euo pipefail

die() { echo "issue-sync: $*" >&2; exit 1; }

ISSUE_NUMBER="${1:?issue number required}"

# Derive owner/repo and platform from the origin remote unless overridden via env.
REMOTE_URL="$(git remote get-url origin 2>/dev/null || true)"
if [ -z "${REPO:-}" ]; then
  # strip protocol/host and trailing .git from both https and ssh forms
  REPO="$(printf '%s' "$REMOTE_URL" | sed -E 's#^[^@]+@[^:/]+[:/]##; s#^[a-z]+://[^/]+/##; s#\.git$##')"
  [ -n "$REPO" ] || die "Could not derive owner/repo from origin. Set REPO=<owner>/<repo>."
fi
if [ -z "${PLATFORM:-}" ]; then
  case "$REMOTE_URL" in
    *gitlab*) PLATFORM="gitlab" ;;
    *)        PLATFORM="github" ;;
  esac
fi
REPO_NAME="${REPO#*/}"
REPO_SLUG="${REPO_SLUG:-$REPO_NAME}"

# Resolve repository root (works regardless of the current working directory).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || true)"
[ -z "$ROOT_DIR" ] && ROOT_DIR="$(cd "$SCRIPT_DIR/../../../../" && pwd)"
[ -d "$ROOT_DIR" ] || die "Unable to resolve repository root from ${SCRIPT_DIR}."

OUT_DIR="${ROOT_DIR}/.TemporaryItems/Issues/${REPO_SLUG}/issues"
OUT_FILE="${OUT_DIR}/${ISSUE_NUMBER}.md"
mkdir -p "$OUT_DIR"

command -v jq >/dev/null 2>&1 || die "Missing required command: jq."

# Fetch the issue as JSON, normalizing both platforms to a common shape:
#   { number, title, body, state, url, labels:[name], assignees:[login], comments:[{author,createdAt,body}] }
if [ "$PLATFORM" = "gitlab" ]; then
  command -v glab >/dev/null 2>&1 || die "Missing required command: glab."
  # glab issue view returns JSON with -F json; comments come from a separate notes call.
  ISSUE_RAW="$(glab issue view "$ISSUE_NUMBER" -R "$REPO" -F json)" || die "Failed to fetch issue #${ISSUE_NUMBER}."
  ISSUE_JSON="$(echo "$ISSUE_RAW" | jq '{
    number: .iid, title: .title, body: (.description // ""), state: .state, url: .web_url,
    labels: (.labels // []),
    assignees: ((.assignees // []) | map(.username)),
    comments: []
  }')"
else
  command -v gh >/dev/null 2>&1 || die "Missing required command: gh."
  gh auth status -h github.com >/dev/null 2>&1 || die "GitHub CLI not authenticated. Run \"gh auth login\"."
  ISSUE_RAW="$(gh issue view "$ISSUE_NUMBER" --repo "$REPO" \
    --json number,title,body,comments,state,labels,assignees,url)" \
    || die "Failed to fetch issue #${ISSUE_NUMBER} from ${REPO}."
  ISSUE_JSON="$(echo "$ISSUE_RAW" | jq '{
    number, title, body: (.body // ""), state, url,
    labels: ((.labels // []) | map(.name)),
    assignees: ((.assignees // []) | map(.login)),
    comments: ((.comments // []) | map({author: .author.login, createdAt: .createdAt, body: (.body // "")}))
  }')"
fi

echo "$ISSUE_JSON" | jq -r --arg repo "$REPO" '
"# Issue #\(.number): \(.title)

Repo: \($repo)
State: \(.state)
URL: \(.url)

## Labels
\( if (.labels | length) > 0 then (.labels | join(", ")) else "None" end )

## Assignees
\( if (.assignees | length) > 0 then (.assignees | join(", ")) else "None" end )

## Description

\(.body)

## Comments

\( if (.comments | length) > 0
   then (.comments | map("### \(.author) — \(.createdAt)\n\n\(.body)") | join("\n\n"))
   else "No comments." end )

## AI Notes

-
"' > "$OUT_FILE"

# Print the path for downstream tooling.
echo "$OUT_FILE"
