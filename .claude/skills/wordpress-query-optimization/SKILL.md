---
name: wordpress-query-optimization
description: >
  Audit WordPress plugin file(s) for database query performance issues: uncached queries on
  always-run admin hooks, N+1 queries in loops, missing $wpdb->prepare(), blocking work on
  save/update requests (post saves, options/settings saves, term/user saves, activation/import), and
  stale cache after writes. Use when reviewing a file for DB performance or when a
  feature is slow on admin pages, post save, or listing screens.
---

# WordPress Query Optimization

Audit the target file(s) for the common WordPress database performance problems and propose fixes.

This skill is vendor-neutral: the object-cache group used in fixes is resolved per **Project
conventions**, not hardcoded.

## Project conventions (resolve before suggesting fixes)

Resolve the **object-cache group** in order, first hit wins:

1. **`AGENTS.md` / `CLAUDE.md`** — the documented cache group.
2. **Repo docs**.
3. **The code itself** — the group argument on existing `wp_cache_get/set/delete` calls.

Use `<cache-group>` below as the placeholder for the resolved value. If the plugin has no
established group, recommend introducing one named after the plugin and using it consistently.

## What to check

### 1. Uncached queries on always-run hooks

Look for `$wpdb->get_*` / `$wpdb->query` / `WP_Query` / `get_posts` inside callbacks that run on
**every** request or admin page load:
- `init` / `admin_init` callbacks (especially before any `is_admin()` / screen guard)
- `admin_enqueue_scripts`
- `admin_menu`, `wp_loaded`, or any hook firing site-wide

These tax pages unrelated to the feature.

**Fix**: wrap in `wp_cache_get()` / `wp_cache_set()` with the `<cache-group>` group, or defer the
work to a lazy code path (only when the relevant screen/feature is active).

### 2. N+1 queries in loops

Look for DB calls inside `foreach` / `array_map` / `while` over IDs (posts, terms, users, custom rows).

**Fix**: collect all IDs first, then run a single batched query (`WHERE id IN ( … )`) built with
`$wpdb->prepare()`; or prime caches up front (`_prime_post_caches()`, `update_meta_cache()`).

### 3. Missing `$wpdb->prepare()`

Any variable interpolated into a SQL string.

**Fix**: use `$wpdb->prepare()` with placeholders — required even where the PHPCS rule is silenced.

### 4. Blocking operations on a write/save request

Look for heavy synchronous work — many DB writes, full-table scans, regeneration jobs, or remote
HTTP calls — inside a hook that fires on a user-facing write request, so the user waits on it. This
is not limited to post saves; common offenders include:
- **Post saves**: `save_post` / `wp_insert_post` / `transition_post_status` / `edit_post`.
- **Options & settings saves**: `update_option` / `add_option` / `updated_option` /
  `update_option_{option}` / `pre_update_option_{option}`, Settings-API save handlers, and
  `admin_post_*` / `admin-ajax` / REST settings-update callbacks.
- **Term / user / comment / meta saves**: `created_term`/`edited_term`, `profile_update`/`user_register`,
  `wp_insert_comment`, `updated_{post|term|user}_meta`.
- **Activation / upgrade / import**: `register_activation_hook`, `upgrader_process_complete`, importers.

Anywhere a save handler does work disproportionate to "persist this change," the request blocks.

#### Over-eager cache purges / regenerations

A specific, very common offender: triggering an **expensive cache purge or asset regeneration too
often** — on every admin page load, or on a frequent/repeated action — when it only needs to happen
once, scoped, or later. Symptoms: a full cache flush or a rebuild (minified assets, critical CSS,
sitemaps, transients, object-cache groups) fired from `init`/`admin_init`/`admin_enqueue_scripts`,
or fired once per item inside a loop or per save in a burst of saves.

**Fix**:
- **Scope the purge** to what actually changed (one post/term/URL/group) instead of a global flush.
- **Debounce / coalesce** repeated triggers: set a "purge needed" flag and run a single purge once at
  `shutdown` or via a single scheduled event, rather than purging on each call.
- **Defer** the regeneration to a background job (see the fix below) so the request doesn't wait on it.
- **Guard the trigger** so it doesn't fire on unrelated page loads or re-fire from its own writes.

**Fix (general)**: persist only the minimal payload synchronously, then schedule the heavy work as a
background job (`wp_schedule_single_event()` / WP-Cron / Action Scheduler / the repo's queue) so the
request returns immediately. Make remote HTTP calls non-blocking or move them off the request path.
Guard re-entrancy (e.g. `update_option` handlers that write options can re-fire) to avoid loops.

### 5. Stale cache after writes

Look for `wp_cache_set()` without a matching `wp_cache_delete()` on the update/delete paths.

**Fix**: call `wp_cache_delete( $key, '<cache-group>' )` (or bump a cache version key) after any
write that invalidates cached data.

## Workflow

1. Read the target file(s) in full.
2. Resolve the `<cache-group>` per Project conventions.
3. Walk checks 1–5; for each issue note severity, line, and the concrete fix.
4. Provide the fix as an inline before/after diff.

## Output format

```
## Query Audit: {file path}

### Issues found

| Severity | Line | Issue | Fix |
|----------|------|-------|-----|
| HIGH | 42 | Uncached SQL in admin_init | Wrap in wp_cache_get/set (<cache-group>) |
| MEDIUM | 88 | N+1 query in foreach | Batch with WHERE IN + $wpdb->prepare() |
| HIGH | 120 | Missing $wpdb->prepare() | Use prepare() |

### Recommended changes
[before/after diff per fix]
```
