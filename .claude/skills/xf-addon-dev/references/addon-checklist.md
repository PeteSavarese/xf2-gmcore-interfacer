# Addon Checklist

## Scope

1. Confirm addon path under `src/addons/<Vendor>/<Addon>/`.
2. Confirm whether the change is code-only, data-only, or mixed.

## Implementation

1. Use `xf-make:*` for creating addon artifacts where possible.
2. Keep behavior changes in PHP classes, not in generated exports.
3. Place install/upgrade logic in `Setup.php` with versioned steps.
4. Follow addon namespace and class layout conventions.
5. Use class extensions to extend core functionality rather than editing core files.

## Setup.php Upgrade Steps

1. Upgrade methods follow the naming pattern: `upgrade<versionId>Step<n>()` (e.g., `upgrade1000070Step1()`).
2. Each step should perform a single isolated action (e.g., add a table, add a column, run a data migration).
3. Steps run sequentially: `Step1`, `Step2`, `Step3`, etc.
4. Long-running data migrations should process in batches (return a step offset to resume).
5. Schema changes and data migrations should be in separate steps.

## Class Extensions

1. Use `xf-make:extension` to create extensions. It generates the class with the correct `XFCP_<ClassName>` parent.
2. The `XFCP_` prefix is a type-hinted proxy to the parent class, providing full IDE support.
3. Extension execution order is controlled by the `execute_order` field.
4. Prefer class extensions over code event listeners when modifying class behavior. Code event listeners are better for cross-cutting concerns at specific execution points.
5. To add a relation to a core entity, extend `getStructure()` in the class extension and append to the relations array.
6. To add a column to a core entity, use the `entity_structure` code event listener or a class extension of the entity, plus a schema alteration in `Setup.php`.

## Content Types and Handlers

1. New content types (for reactions, alerts, attachments, etc.) are registered via `xf_content_type_field` entries mapping content type strings to handler classes.
2. No `xf-make:*` command exists for content types currently. Set up manually in `_output/` and `Setup.php`.
3. Permissions are also manual artifacts currently. They can be created in the admin control panel or manually in `_output/`.

## Widgets

1. XenForo has a widget system. Addons can register new widget types with render methods that output templates.
2. No dedicated `xf-make:widget` command currently exists.

## Lifecycle

1. Sync/import development output when applicable.
2. Never use export as the first step for creation.
3. If manual filesystem edits were made in `_output/`, run scoped import (`xf-dev:import --addon=Vendor/Addon`).
4. Only run XML packaging/export when release/build is explicitly requested.
5. Verify expected `_output/` files changed and unrelated files did not.
6. For release/build tasks, ask before running `xf-addon:bump-version` because the version may already be set.
