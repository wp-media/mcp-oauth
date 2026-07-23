---
name: wordpress-ability
description: >
  Add or modify a WordPress ability using the WordPress Abilities API (WP 6.9+, wp_register_ability)
  and expose it to the REST API and MCP. Covers registration, ability categories, permission checks,
  input/output schema rigor, annotations (readonly/destructive/idempotent), optional usage
  telemetry, and testing. Use when adding any new ability or changing an existing one.
---

# WordPress Ability (Abilities API + MCP)

The WordPress **Abilities API** (`wp_register_ability`, available from WordPress 6.9) is core
WordPress, so this skill is portable across plugins. Where it needs a repo-specific value — the
vendor slug, the registration mechanics, the default capability, the telemetry wrapper — resolve it
per **Project conventions**; do not assume a particular plugin's architecture.

## Project conventions (resolve before writing)

Resolve each in order, first hit wins:

1. **`AGENTS.md` / `CLAUDE.md`** — vendor slug (for `<vendor>/ability-name`), where abilities and
   categories are registered, the hook-registration style (direct vs a Subscriber/ServiceProvider/
   hooking-trait abstraction), the default capability for `check_permissions()`, and the
   analytics/telemetry wrapper + event-naming convention (if the plugin tracks usage).
2. **Repo docs**.
3. **The code itself** — read an existing ability and its subscriber/registration as the style
   reference; grep `wp_register_ability(` and `wp_abilities_api_init`.

Use `<vendor>`, `<capability>`, and `<text-domain>` below as placeholders for the resolved values
(text domain resolves the same way — `AGENTS.md`, else the plugin header / existing `__()` calls).

## Core functions

```php
wp_register_ability( string $name, array $args );        // register an ability
wp_register_ability_category( string $slug, array $args ); // register an ability category
```

Always guard for WordPress < 6.9, where these functions don't exist:

```php
if ( ! function_exists( 'wp_register_ability' ) ) {
    return;
}
```

## Registration

- **One ability = one class.** Place it adjacent to the feature it belongs to.
- Register the ability on the **`wp_abilities_api_init`** hook; register any new category on
  **`wp_abilities_api_categories_init`** (register the category before the abilities that use it).
- Wire those hooks the way the repo does: directly with `add_action`, or via the repo's
  registration abstraction (Subscriber/ServiceProvider/hooking trait) — follow the existing pattern,
  don't introduce a new one.
- **Ability name** must be unique and follow `<vendor>/ability-name` — lowercase, hyphen-separated,
  using the plugin's vendor slug.
- Every ability belongs to a **category**. If the needed category doesn't exist, register it first.

```php
wp_register_ability(
    '<vendor>/get-things',
    [
        'category'            => '<vendor>-things',
        'label'               => esc_html__( 'Get things', '<text-domain>' ),
        'description'         => esc_html__( 'Returns the active things.', '<text-domain>' ),
        'input_schema'        => [
            'type'                 => 'object',
            'default'              => [],
            'properties'           => [ /* ... */ ],
            'additionalProperties' => false,
        ],
        'output_schema'       => [ 'type' => 'object', 'properties' => [ /* ... */ ] ],
        'permission_callback' => [ $this, 'check_permissions' ],
        'execute_callback'    => [ $this, 'execute' ],
        'meta'                => [
            'show_in_rest' => true,           // expose to the REST API
            'mcp'          => [ 'public' => true ], // expose to MCP (unless explicitly not wanted)
            'annotations'  => [
                'readonly'    => true,   // set explicitly per ability
                'destructive' => false,
                'idempotent'  => true,
            ],
        ],
    ]
);
```

> Expose to REST/MCP (`show_in_rest`, `mcp.public`) **unless explicitly required otherwise**.

## Permissions

`check_permissions()` must verify the current user's capabilities — use
`current_user_can( '<capability>' )` (the plugin's management capability by default), **not** a bare
`manage_options` where the plugin defines its own capability. Never weaken this except when
explicitly required.

## Input / output schema rigor

- `input_schema` declares `additionalProperties: false`; mark required props with `required`.
- The parameters `execute()` consumes must match the declared `input_schema`; the value it returns
  must match the declared `output_schema`.
- Inside `execute()`, **cast and validate** every input before use (`(bool)`, `(int)`, `absint()`,
  allow-list checks) — do not trust the caller.

## Annotations

Set `readonly`, `destructive`, and `idempotent` **explicitly** for every ability; never inherit
defaults silently. Read-only abilities: `readonly: true, destructive: false, idempotent: true`.
For mutating abilities, decide `destructive` and `idempotent` deliberately.

## The ability is a thin adapter (no duplication)

An ability accepts input, delegates to existing plugin code, and shapes the output. It is **not** a
place to reimplement logic.

- **grep before you write.** Search the plugin for a service, module method, query, or REST
  controller that already performs the operation, and call it.
- **Zero duplicated SQL / option reads / REST logic.** If `$wpdb->get_results(...)` or
  `get_option(...)` already exists for the same data elsewhere, call that code — don't repeat it.
- A separate Runner/service class is warranted only to **orchestrate or shape** existing code for
  the ability's output schema, not to reimplement it.

## Usage telemetry (only if the plugin tracks usage)

If the plugin records feature/ability usage, follow these backend-agnostic rules (we usually use MixPanel but check **Project conventions** for the concrete
wrapper and event-naming convention if any, or mimic existing abilities usage tracking):

- **Track only inside `execute()`.** WordPress calls `execute()` only after `check_permissions()`
  returns true, so this inherently means **never track on a permission failure**.
- **Reuse an existing event.** If the underlying feature already fires an equivalent tracking event,
  call into that path instead of adding duplicate tracking.
- **Route through the plugin's tracking wrapper**, not a raw analytics-client call.
- Record a **session-context flag** (e.g. MCP session vs web) where the plugin supports it.
- Use the plugin's **event-naming convention** consistently (match existing event names).

If the plugin has no usage tracking, add none.

## Testing

Cross-reference `[[wordpress-phpunit-tests]]` for the framework setup (unit vs integration decision,
base classes, mocking). Ability-specific guidance:

- **Unit**: test the `execute()` logic in isolation; mock dependencies; assert it behaves correctly
  across inputs and error branches.
- **Integration**: gate on WordPress version — abilities require **WP 6.9+**, so **skip** integration
  tests when the WordPress under test is older than 6.9. Set up users to exercise permissions: one
  case where the user **has** the capability and one where they **don't**, asserting both outcomes.
  Execute via the registered object: `wp_get_ability( '<vendor>/get-things' )->execute( $input )`,
  and assert the output matches the schema.

## Validation checklist

Before reporting completion:

- [ ] `function_exists( 'wp_register_ability' )` guard present for pre-6.9 compatibility
- [ ] Ability registered on `wp_abilities_api_init`; category on `wp_abilities_api_categories_init`
- [ ] Hooks wired via the repo's existing registration pattern (not a new one)
- [ ] Ability name is `<vendor>/...`, unique, lowercase-hyphenated
- [ ] `permission_callback` and `execute_callback` both provided
- [ ] `check_permissions()` uses `<capability>`, not a bare `manage_options`
- [ ] `additionalProperties: false` on `input_schema`; inputs cast/validated in `execute()`
- [ ] `execute()` I/O matches the declared schemas
- [ ] `readonly` / `destructive` / `idempotent` set explicitly
- [ ] `show_in_rest` + `mcp.public` set (unless explicitly not wanted)
- [ ] No duplicated SQL/option/REST logic — existing code is reused
- [ ] Telemetry (if any) only inside `execute()`, reuses existing events, via the plugin's wrapper
- [ ] Tests added; integration tests gated on WP ≥ 6.9 with has-cap / lacks-cap cases

## Output format

```
## Ability: <vendor>/{ability-slug}

### Files created / modified
- [paths]

### Registration
[where + how it's hooked]

### Validation checklist
[checklist above, marked pass/fail]
```
