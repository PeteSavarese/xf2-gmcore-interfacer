---
name: xf-addon-dev
description: XenForo addon development workflow for implementation and upgrade logic. Use when changes are in src/addons/* (outside src/addons/XF/), addon Setup.php, addon class extensions, addon templates/phrases, or addon-owned runtime code.
---

# XF Addon Development

Read [shared policy](../xf-shared-policy/SKILL.md) for universal rules (runner detection, artifact boundaries, import/export, phrase conventions).

## Workflow

1. Confirm addon owner and target addon ID/path (e.g. `src/addons/Vendor/Addon/`).
2. Use `xf-make:*` commands for new addon artifacts.
3. Treat `_output/` as the development surface. Ignore `_data/*.xml` in normal development.
4. Implement changes using existing addon conventions.
5. Keep install and upgrade logic in `Setup.php` with safe versioned steps.
6. Keep class extension usage consistent with addon extension patterns.
7. Run only the sync/import steps needed for the change.
8. **Before starting work**, ask whether `xf-addon:bump-version` should be run. Version bumps often happen at the start of a development cycle, not just for release/build tasks.

## Lifecycle Checkpoints

1. If templates or phrases changed, run scoped sync/import steps.
2. Do not manually edit `_data/*.xml` during normal development.
3. If filesystem changes were made manually in `_output/`, run scoped import (`xf-dev:import --addon=Vendor/Addon`).
4. Only run XML packaging/export when release/build is explicitly requested.

## Version ID Convention

XenForo version IDs encode version, stability, and release level in a single integer:

| Version ID | Meaning |
|---|---|
| `2030170` | 2.3.1 Stable |
| `2030171` | 2.3.1 Stable patch 1 |
| `2030131` | 2.3.1 Beta 1 |
| `2030152` | 2.3.1 RC2 |
| `2030110` | 2.3.1 Alpha |

Format: `{major}{minor:02d}{patch:02d}{stability}{level}`

Stability digits: 1 = Alpha, 3 = Beta, 5 = RC, 7 = Stable. Level: patch number within that stability.

Addons are recommended to follow this convention. The current version ID is in `addon.json`.

## Reference

Read [references/addon-checklist.md](references/addon-checklist.md) for high-signal checkpoints and common misses.
