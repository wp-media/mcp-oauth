---
name: knowledge-graph
description: >
  Read and refresh a pre-built dependency graph at .claude/graph/dependency-graph.json. Use to
  locate where a symbol is defined, trace dependencies, find where a service is wired, or
  enumerate the handlers/registrations in a module — without re-scanning the codebase from
  scratch. This skill is primarily a reader: the graph is built (and incrementally refreshed) by
  the bundled builder `node .claude/skills/knowledge-graph/scripts/build-graph.js`. Invoke at
  session start (to refresh if stale) and before grep/glob searches for symbol relationships.
---

# Knowledge Graph

A pre-built dependency graph lives at `.claude/graph/dependency-graph.json`. The bundled builder
is `node .claude/skills/knowledge-graph/scripts/build-graph.js` (incremental by default, `--full`
to force a rebuild).

> **Customization (local — in the builder script, not AGENTS.md):** the bundled builder supports
> PHP, JS/TS, and Python out of the box and auto-detects common source roots. Edit its `SCAN_DIRS`
> / language parsers to extend it, or point this skill at a project-specific builder if your repo
> already has one. If no builder is available, skip the graph and fall back to `grep`/`glob`.

This skill has two responsibilities:
1. **Refresh** the graph at session start if it is stale (`base_commit` ≠ `git rev-parse HEAD`).
2. **Read** the graph to answer dependency, namespace, and structure questions instantly.

**Read it before** running grep/glob searches for symbol relationships, namespace exploration, or
dependency tracing. It eliminates redundant file scans and speeds up the first useful response in
any session.

---

## Graph shape

```json
{
  "generated_at": "<ISO timestamp>",
  "base_commit":  "<git SHA>",
  "node_count":   913,
  "nodes": {
    "src/cache/Subscriber.php": {
      "language":  "php",
      "namespace": "App\\Cache",
      "symbols": [
        { "kind": "class", "name": "Subscriber", "extends": [], "implements": ["EventSubscriberInterface"] }
      ],
      "imports": [
        "App\\Events\\EventSubscriberInterface",
        "App\\Cache\\Purge"
      ]
    }
  },
  "symbol_index": {
    "App\\Cache\\Subscriber": "src/cache/Subscriber.php"
  }
}
```

- **`nodes`** — keyed by relative file path. Each node has the language, declared symbols (for languages where they are extracted), and all import/require statements.
- **`symbol_index`** — maps every fully-qualified symbol (class / interface / function / module) to its file path. Use this for instant "where is this defined?" lookups.

---

## Query patterns

### Find where a symbol is defined (zero grep)
```
symbol_index["App\\Cache\\Purge"]
→ "src/cache/Purge.php"
```

### Find where a class/service is wired or registered
Wiring/registration sites import the thing they register. Search for files whose `imports`
contain the target symbol:

```
filter nodes where "App\\Cache\\Purge" ∈ node.imports
→ "src/cache/ServiceProvider.php"
```
Then read that file to see how the dependency is registered.

### Find all handlers/registrations of a kind in a module
Filter nodes where:
- `namespace`/path starts with the module prefix (e.g. `App\Cache`)
- `symbols[*].implements` (or `extends`) contains the marker interface/base class of that kind

### Trace a symbol's full dependency chain
1. Start at `symbol_index["App\\...\\ClassName"]` → get file path
2. Read `nodes[file].imports` → these are its direct dependencies
3. For each dependency, repeat → you get the full dependency tree without reading source

### Verify no unexpected cross-module dependencies
Check `nodes[file].imports` for any symbol that shouldn't be there — e.g. a presentation-layer
module importing a persistence-layer internal is a red flag.

### Narrow a content search before grepping
When a query needs the actual source (e.g. "which files call function X directly"), use the graph
to narrow the file set first — only grep the nodes in the relevant namespace/path prefix.

---

## Keeping the graph fresh

The graph records the git commit it was built from (`base_commit`). If that SHA differs from `HEAD`, run:

```bash
node .claude/skills/knowledge-graph/scripts/build-graph.js
```

The script is incremental — it only re-parses files changed since `base_commit`. Use `--full` to force a complete rebuild.

**When to refresh:**
- At the start of every issue workflow session.
- After merging a branch with structural changes (new symbols, module moves).
- Before an architecture review session.

---

## Supported languages

The bundled builder extracts symbols and imports for:

| Language | What is extracted |
|---|---|
| PHP | `namespace`, `class`/`interface`/`trait`/`enum` declarations (with `extends`/`implements`), `use` imports (including grouped `\{A, B}` forms) |
| TypeScript / JavaScript | `import` (static + dynamic) and `require()` sources |
| Python | `class`/`def` declarations, `import` / `from … import` sources |

Extend the builder for additional languages as needed.

---

## Practical workflow (issue implementation)

Before writing a single line of code for an issue:

1. Check `base_commit` vs `HEAD` — refresh if stale.
2. Use `symbol_index` to locate all symbols involved in the fix.
3. For each, read `nodes[file].imports` — know the dependency chain before touching it.
4. Find the wiring/registration site via the import search above — know where to add/modify the binding.
5. List the related handlers/registrations in the module — know which ones may need updating.
6. Only then open the actual source files (now you know exactly which ones to read).
