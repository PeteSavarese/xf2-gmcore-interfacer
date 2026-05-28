---
name: xf-core-dev
description: Core XenForo framework development workflow and conventions. Use when changes affect src/XF/, js/xf/, src/addons/XF/, install/upgrade internals, core entities, repositories, services, or controllers.
---

# XF Core Development

Read [shared policy](../xf-shared-policy/SKILL.md) for universal rules (runner detection, artifact boundaries, import/export, phrase conventions).

## Workflow

1. Confirm scope is core (not addon-owned). If unclear, ask.
2. Locate the existing pattern in nearby core code before implementing.
3. Apply XenForo architectural patterns:
   - Business logic in **services**.
   - Query composition in **repositories** and **finders**.
   - Controllers stay orchestration-focused (thin controllers).
4. Keep changes minimal and backwards compatible.

## Validation

1. Run targeted checks first (`php-cs-fixer`, `phplint`, `phpstan`), then broader checks if needed.
2. If behavior or schema changes are introduced, verify upgrade/install path implications.

## Verification Commands

```bash
composer run phplint          # Lint PHP
composer run php-cs-fixer:fix # Fix PHP style
composer run phpstan          # Static analysis
composer run phpunit          # Run tests
npm run eslint                # Lint JavaScript
```

## Reference

Read [references/core-map.md](references/core-map.md) for path ownership and quick checkpoints.
