---
name: wordpress-compliance
description: >
  Check a change against WordPress.org plugin rules, the repository's PHPCS standards, and a
  WordPress security checklist. Use when modifying templates, admin UI, output, hooks, REST routes,
  database access, plugin metadata, sanitization, or escaping — or when reviewing a diff for
  WordPress compliance and security. Covers output escaping, input sanitization, capability and
  nonce checks, prepared SQL, REST permission callbacks, and admin client-side hygiene.
---

# WordPress Compliance & Security

Ensure a change is compatible with:

- **WordPress Plugin Check** and WordPress.org plugin guidelines
- The repository's **PHPCS** configuration (e.g. WordPress Coding Standards)
- Sound **WordPress security** practice (escaping, sanitization, authorization, prepared SQL)

This skill is vendor-neutral. Where it needs a repo-specific value (text domain, capability map,
meta-key prefix, PHPCS ruleset), resolve it via the order in **Project conventions** below — never
assume a specific plugin's conventions.

## Project conventions (resolve before applying)

Resolve each repo-specific value in this order, using the first hit:

1. **`AGENTS.md` / `CLAUDE.md`** at the repo root.
2. **Repo docs** — `README`, `docs/`, contributing/architecture notes.
3. **The code itself** as a fallback:
   - **Text domain** → the `Text Domain:` header in the main plugin file, or the domain passed to
     `load_plugin_textdomain()` / the second argument of existing `__()`/`esc_html__()` calls.
   - **Capability map** → grep `current_user_can(`, `map_meta_cap`, `add_cap(`, `'capability' =>`.
   - **Meta-key prefix** → the prefix on existing `add_post_meta`/`update_post_meta` keys.
   - **PHPCS ruleset** → `phpcs.xml`, `phpcs.xml.dist`, `.phpcs.xml`, or `.phpcs.xml.dist`.

Use `<text-domain>`, `<capability>`, and `<meta-prefix>` below as placeholders for the resolved values.

## What to verify

### Output escaping (escape late, at the point of output)

| Context | Function |
|---|---|
| HTML text | `esc_html()` / `esc_html__()` / `esc_html_e()` |
| HTML attribute | `esc_attr()` / `esc_attr__()` |
| URL | `esc_url()` (use `esc_url_raw()` for storage/redirects) |
| Allowed HTML markup | `wp_kses_post()` or `wp_kses()` with an explicit allow-list |
| JS-embedded data | `wp_json_encode()` / `esc_js()` |

- Never echo a raw variable. Every dynamic value printed to the page must be escaped for its context.
- Translations that emit markup must still be escaped (`esc_html__()`, not bare `__()` into `echo`).

### Input sanitization & validation

- Sanitize every value read from `$_GET`/`$_POST`/`$_REQUEST`/`$_SERVER`/`$_COOKIE` and from REST
  params before use: `sanitize_text_field()`, `absint()`, `sanitize_key()`, `sanitize_email()`,
  `wp_unslash()` (before sanitizing super-globals), etc.
- Validate, don't just sanitize, when a value has a known shape (allowed enum, numeric range, ID exists).
- Verify a **nonce** for state-changing form/AJAX requests (`check_admin_referer()`,
  `wp_verify_nonce()`, `check_ajax_referer()`). REST routes use the cookie nonce via
  `permission_callback` instead.

### Authorization (capabilities)

- Guard every privileged action with `current_user_can( '<capability>' )`. Prefer the plugin's
  registered custom capability over a bare `manage_options` where the repo defines one (resolve the
  capability map per **Project conventions**).
- Every `register_rest_route()` must declare a real `permission_callback` — **never**
  `'permission_callback' => '__return_true'` for anything that reads private data or mutates state.

### Database access

- Use `$wpdb->prepare()` for every query that interpolates a variable — even if the project's PHPCS
  rule for it is silenced. Never concatenate user input into SQL.
- Prefix new post/term/user meta keys and option names with `<meta-prefix>` (the plugin's namespace).

### Forbidden / discouraged APIs

- No deprecated WordPress functions; no direct PHP superglobal use without `wp_unslash()` + sanitize.
- No `eval()`, no `extract()`, no remote code execution, no unsafe deserialization of untrusted input.
- Store secrets out of the codebase; never log credentials or tokens.

### Admin output & client-side hygiene

These hold regardless of the admin stack (React SPA or vanilla DOM/templates):

- Escape all output rendered in PHP/template files for its context (see the escaping table).
- Pass server data to JS via `wp_localize_script()` or `wp_add_inline_script()` — **never** inline a
  raw `<script>` data blob, and **never** hardcode nonces (localize them).
- No unsafe `innerHTML` with unsanitized data — prefer `textContent` / `createElement`, or escape
  before injecting.
- REST/AJAX calls from the admin send the localized nonce and handle errors explicitly (no silent
  failures).
- No `console.log` / debug output left in shipped JS; no `error_log()` / `var_dump()` / `print_r()`
  left in shipped PHP.

> The repo may layer its own admin conventions on top (e.g. "no jQuery — native DOM only", a
> specific enqueue/Subscriber pattern, React-only settings). Read those from `AGENTS.md`/docs and
> apply them in addition to the universal rules above; do not invent a stack preference here.

## Anti-patterns (flag these)

- Echoing raw/unescaped variables, or escaping early then mutating the value.
- A REST route with `__return_true` (or no) `permission_callback`.
- Raw interpolated SQL without `$wpdb->prepare()`.
- Reading a superglobal without `wp_unslash()` + sanitization.
- Missing capability/nonce check on a state-changing action.
- Hardcoded nonces or secrets; debug statements left in code.
- New meta/option keys without the plugin prefix.

## Workflow

1. **Scope the change.** If reviewing a branch, get the diff against the base branch (resolve the
   base branch from `AGENTS.md`/git). If invoked on specific files, read them in full.
2. **Resolve project conventions** (text domain, capability map, meta prefix, PHPCS ruleset) per the
   order above.
3. For each changed **PHP** file: walk the escaping, sanitization, authorization, SQL, and
   forbidden-API checks.
4. For each changed **JS/template** file: walk the admin output & client-side hygiene checks.
5. If a PHPCS ruleset exists, note any rule the change would violate; recommend running PHPCS.

## Output format

```
## WordPress Compliance Review

**Scope**: [branch diff | files]
**Text domain**: <text-domain>   **Cap map source**: [AGENTS.md | docs | code]

### BLOCKERs
1. `path/to/file.php:42` — REST route `/<ns>/resource` has `permission_callback => __return_true`.
   Fix: add a real capability check, e.g. `current_user_can( '<capability>' )`.

### NICE-TO-HAVEs
1. `path/to/view.php:88` — output `$title` is echoed unescaped. Wrap in `esc_html()`.

### PHPCS
- [ruleset found at <path> | not found] — [violations to expect, or "run `phpcs` to confirm"]

### Verdict
APPROVED / CHANGES REQUESTED
```

Classify each finding as **BLOCKER** (security hole, missing capability/nonce, unescaped output,
raw SQL, debug output shipped) or **NICE-TO-HAVE** (style, naming, minor hygiene).
