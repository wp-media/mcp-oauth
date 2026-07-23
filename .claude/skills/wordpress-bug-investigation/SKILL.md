---
name: wordpress-bug-investigation
description: >
  Trace a WordPress plugin bug from symptom to root cause along the hook → handler → service/query
  → DB/REST execution path, and trace a feature's full data flow (UI → REST → PHP → DB → back). Use
  when a bug is reported or unexpected behaviour is observed, or when you need to understand how a
  feature moves data end to end before changing it.
---

# WordPress Bug Investigation & Data-Flow Tracing

Trace the execution path from the reported symptom to the root cause, then propose the minimal fix.
The same tracing discipline answers "where does this break?" and "how does this feature work
end-to-end?".

This skill is vendor-neutral: capability map, object-cache group, REST namespace, controller and
JS locations, and the hook-registration style are resolved per **Project conventions**, not assumed.

## Project conventions (resolve before tracing)

Resolve each in order, first hit wins:

1. **`AGENTS.md` / `CLAUDE.md`** — feature/module entry points, hook-registration style
   (direct `add_action`/`add_filter` vs an abstraction like a Subscriber/ServiceProvider or a
   hooking trait), REST namespace + controller location, admin JS/data-layer location, capability
   map, object-cache group, background-process state convention.
2. **Repo docs** — architecture/contributing notes.
3. **The code itself**:
   - **Hooks** → grep `add_action(`, `add_filter(`, or the repo's hooking abstraction.
   - **REST** → grep `register_rest_route(` for the namespace and controllers.
   - **Capabilities** → grep `current_user_can(` / `map_meta_cap`.
   - **Cache** → grep existing `wp_cache_get/set/delete` for the group argument.

## Bug investigation

### 1. Clarify the symptom

If not fully described, ask:
- Visible behaviour vs expected behaviour?
- Which area: PHP backend, admin UI / JS, REST API, background processor (cron/queue), front-end output?
- Which feature/module?
- When does it happen: page load, save, REST request, cron run, activation?

### 2. Locate the entry point

- Find the feature's bootstrap (resolve module/feature entry-point location per conventions).
- Identify where its hooks are registered — directly via `add_action`/`add_filter`, or via the
  repo's hooking abstraction. List the hook → callback pairs relevant to the symptom.

### 3. Trace the execution path

- Follow hook → callback → service/query chain.
- **REST bugs**: find the controller and `register_routes()`; match the route; check the
  `permission_callback`, the args schema (`sanitize_callback`/`validate_callback`), and the handler.
- **Admin UI bugs**: enqueue method → localized data passed to JS → the JS service/handler that
  consumes it.
- **Background/cron bugs**: the scheduled hook, its handler, and any persisted state/lock.

### 4. Check the common failure points

- **Permissions** — is the `permission_callback` / capability check correct and present?
- **Sanitization** — is input from superglobals/REST params sanitized & unslashed before use?
- **SQL** — is `$wpdb->prepare()` used? Any N+1 loop? (see `[[wordpress-query-optimization]]`)
- **Caching** — stale cache returned? Cache invalidated after writes? (correct cache group?)
- **State / async** — a background-process state option or lock stuck from a crashed run?
- **JS** — the service call, the hook/handler that calls it, and the component/handler rendering the result.

### 5. Identify the root cause

State the exact cause in one sentence, with the specific `file:line`.

### 6. Propose the minimal fix

- Smallest change that fixes the cause; do not refactor surrounding code.
- Show the before/after inline. Note any edge cases the fix introduces.

### Bug output format

```
## Bug Investigation

**Symptom**: [one line]
**Area / feature**: [...]
**Execution path**: Hook → Handler → [chain]

### Root cause
`path/to/file.php:line` — [exact explanation]

### Fix
**File**: `path/to/file.php`
Before:
[snippet]
After:
[snippet]

### Edge cases / follow-up
- [notes]
```

## Data-flow tracing

Use this mode to map a feature end to end (e.g. before modifying it, or to explain it).

1. **Entry point (UI or trigger)** — which component/button/handler starts the flow; which JS
   service or function is called.
2. **Transport → REST/AJAX** — the full route (e.g. `POST /<namespace>/resource`), the controller
   handling it, the `permission_callback`.
3. **PHP handler** — what the callback does step by step; which service/query it calls; what SQL
   runs (with table names); whether results are cached (and with what key/group).
4. **Response back** — what PHP returns; how the JS service transforms it; how the UI updates state.
5. **Async** — any background processor / cron event triggered after the response returns.

### Data-flow output format

```
## Data Flow: [feature]

### Trigger
[Component/handler:line] — user action / mount / submit

### Client → REST
Service: [service/function]
Route: [METHOD /<namespace>/endpoint]
Payload: { ... }

### PHP handler
Controller: [file::method] (line)
Permission: [permission_callback]
Steps: 1. sanitize  2. call service  3. SQL: SELECT ... FROM <table> WHERE ...  4. cache: <group>/<key>  5. return { ... }

### REST → Client
Response shape: { ... }
State update: [how the UI updates]

### Async (if any)
Event: [cron/queue hook]  Processor: [class/handler]
```
