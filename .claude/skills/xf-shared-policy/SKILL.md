---
name: xf-shared-policy
description: Universal XenForo development policy shared by all skills. Covers command runner detection, artifact boundaries, import/export rules, xf-make preference, and phrase conventions. Load this alongside any other XF skill.
---

# XF Shared Policy

Universal rules that apply to all XenForo development work. Individual skills reference this for shared conventions.

## Command Runner Detection

1. Check the repository root for `compose.yaml`.
2. If `compose.yaml` exists, treat as **Docker mode** and use the `xf` wrapper for all commands.
3. If `compose.yaml` does not exist, treat as **non-Docker mode** and use `php cmd.php` directly.
4. If **either** runner fails (command not found, Docker not running, etc.), **ask the user** before attempting an alternative. Do not silently fall back.

### Invocation forms

| Action | Docker mode | Non-Docker mode |
|---|---|---|
| XF CLI command | `xf xf-make:template ...` | `php cmd.php xf-make:template ...` |
| Composer command | `xf composer -- run php-cs-fixer:fix` | `composer run php-cs-fixer:fix` |
| PHP command | `xf php -- -v` | `php -v` |

## Artifact Creation

1. Prefer `xf-make:*` commands for creating XF artifacts (templates, phrases, routes, options, classes, entities, etc.).
2. Do not create artifacts by manually adding filesystem files when a `xf-make:*` command exists for that artifact type.
3. If no suitable `xf-make:*` command exists, state that clearly and ask for clarification before manual creation.
4. `xf-make:*` commands write both database records and `_output/` files. After running a `xf-make:*` command, do **not** follow up with `xf-dev:import` or `xf-dev:export` — the artifact is already fully created.

## Artifact Boundaries

1. `_output/` directories are development output. Edit these during development.
2. `_data/*.xml` files are release/packaging artifacts. Do not edit these during normal development.
3. Ignore `_data/*.xml` unless the task explicitly requests release or build packaging.

## Import and Export Policy

1. **Never** use `xf-dev:export` as a first-step creation workflow. It exports database content to the filesystem and is destructive if the content exists only on the filesystem.
2. `xf-make:*` commands handle both database insertion **and** `_output/` file creation. There is no need to run `xf-dev:export` after using `xf-make:*`.
3. **Never run `xf-dev:export` during normal development.** It is only invoked internally by `xf-addon:build-release` for release packaging. Do not run it manually.
4. If manual filesystem edits were made to `_output/`, run a scoped import: `xf-dev:import --addon=Vendor/Addon`.
5. Use scoped operations (`--addon=`) whenever possible.
6. Only run XML packaging/export (`xf-addon:build-release`) for explicit release/build tasks.

## Phrase Boundary Convention

1. Double-tilde markers `~~...~~` denote phrase boundaries in templates and source files (e.g. `~~Page title~~`, `return '~~Error message~~';`).
2. Preserve these markers. Do not remove or normalize them unless explicitly asked.
3. Do not block development on immediate phrase creation if markers are present.
4. In PHP/source files, if phrase intent is unclear for a new user-facing string, ask before adding or removing markers.
5. Unmarked literal strings may be acceptable during development; phrase tooling can convert them later.
6. There is no JS phrase support. Do not apply phrase markers to JS strings.

## General Principles

1. Keep changes minimal and backwards compatible unless explicitly approved.
2. Follow existing XenForo conventions. Match the patterns in nearby code.
3. Do not modify vendor or generated artifacts directly when a project workflow exists.
4. Run relevant verification for the changed surface area before finalizing.
5. Preserve existing behavior unless the task explicitly requests behavior change.
