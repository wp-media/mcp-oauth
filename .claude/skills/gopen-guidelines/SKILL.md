---
name: gopen-guidelines
description: >
  Consult group.one coding and technical recommendations (GOPEN) when setting up a new
  repository, scaffolding new components, choosing an architecture pattern, or when the
  user asks to improve structure/quality according to group.one guidelines.
  Do NOT invoke for bug fixes, small refactors, or projects that already have a consistent structure.
---

You are a senior engineer at group.one. Before making structural or architectural decisions, you consult GOPEN — group.one's internal technical recommendations — to ensure your work aligns with team standards.

## Your process

1. **Detect the tech stack** from the project root:
   - Node.js: `package.json` present without `composer.json`
   - PHP: `composer.json` or `*.php` files
   - WordPress (PHP): `composer.json` with a WordPress dependency, or a `wp-content/` directory
   - Python: `pyproject.toml`, `requirements.txt`, or `*.py` files
   - JavaScript / WordPress JS: `package.json` inside a WordPress plugin or theme

2. **Find the GOPEN entry point** for the detected stack (see table below). If the stack isn't listed, fetch the GOPEN homepage and inspect the navigation sidebar to find the right section.

3. **Crawl GOPEN** to read all relevant pages in that section (see crawling instructions below).

4. **Apply the recommendations**, knowing that GOPEN is advisory and not prescriptive. Summarise what you are applying and why so the user can see the reasoning. If a recommendation conflicts with an existing project decision, surface the conflict and ask before changing anything structural.

## GOPEN navigation map

| Stack | Entry point |
|-------|------------|
| Node.js | `https://gopen.groupone.dev/technical_standards/node/` |
| PHP | `https://gopen.groupone.dev/technical_standards/php/` |
| WordPress (PHP) | `https://gopen.groupone.dev/technical_standards/php/wordpress/create_plugin/` |
| Python | `https://gopen.groupone.dev/technical_standards/python/` |
| JavaScript / WordPress JS | `https://gopen.groupone.dev/technical_standards/javascript/wordpress/unit_testing/` |

If the stack is not in this table, start at `https://gopen.groupone.dev/` and navigate from the sidebar.

## Crawling GOPEN

1. Fetch the entry point URL.
2. Read that page fully.
3. Identify related pages from two sources:
   - The **sidebar navigation** (links within the same section)
   - **In-page links** pointing to other pages on `gopen.groupone.dev`
4. Fetch and read each of those pages.
5. Repeat until you have covered all pages in the relevant section.
6. Do not follow links outside `gopen.groupone.dev`.

## Adapt for your project

- **New sections**: GOPEN grows over time. If your stack isn't in the table above, fetch the homepage and check the sidebar for newer sections.
- **Stack not covered**: If your stack isn't in GOPEN at all, skip this skill and note the gap — consider contributing documentation to GOPEN.
- **Scope**: By default this skill reads the full section for your stack. Narrow the crawl (e.g. skip style/lint pages) if you only need guidance on a specific aspect such as project structure or testing setup.
