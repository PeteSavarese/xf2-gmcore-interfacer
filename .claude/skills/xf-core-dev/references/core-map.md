# Core Scope Map

## Primary Core Paths

| Path | Responsibility |
|---|---|
| `src/XF/Entity/` | Data models, column definitions, relations |
| `src/XF/Repository/` | Complex queries, data access logic |
| `src/XF/Service/` | Business logic, multi-step operations |
| `src/XF/Finder/` | Chainable query builders |
| `src/XF/Pub/Controller/` | Public-facing controllers |
| `src/XF/Admin/Controller/` | Admin panel controllers |
| `src/XF/Api/Controller/` | API controllers |
| `src/XF/Install/` | Install and upgrade logic |
| `src/XF/Job/` | Background job processing |
| `js/xf/` | Core JavaScript |
| `src/addons/XF/` | Core addon (templates, phrases, routes, options) |

## Quick Checklist

1. Reuse an existing pattern from nearby core files.
2. Keep controller actions thin — delegate to services.
3. Keep query logic in the repository/finder layer.
4. Keep business rules in the service layer.
5. Confirm backwards compatibility impact for public methods and extensibility points.
6. If adding a new entity, ensure structure definitions and relations follow existing conventions.

## Entity Conventions

1. Entities define column structure and relations in a static `getStructure()` method.
2. `getStructure()` must stay in sync with the schema in `src/XF/Install/Data/MySql.php`.
3. Column validation uses `verify<ColumnName>()` methods and `_preSave()`.
4. Entity relations are `TO_ONE` and `TO_MANY` only.
5. Skeleton Finder classes are auto-created with entities for type-hinting support.

## Repository Conventions

1. Methods prefixed with `find` return a Finder for further chaining by the caller.
2. Methods prefixed with `get` return results directly (entities or collections).

## Service Conventions

1. Services extend `\XF\Service\AbstractService`.
2. Common pattern: configure via setters, then call `save()` or a domain-specific execute method.
3. The `ValidateAndSavableTrait` provides `validate(&$errors)` and `save()`. `save()` calls `validate()` automatically, but they can be run separately.

## Install/Upgrade Paths

1. Base installation schema: `src/XF/Install/Data/MySql.php`.
2. Versioned upgrade scripts: `src/XF/Install/Upgrade/` (one file per version).

## Caching

1. **Data Registry**: Core system for storing frequently accessed data (`\XF::registry()`).
2. **Simple Cache**: Lighter-weight cache good for addon storage (`\XF::app()->simpleCache()`).
3. Consider both long-term (persistent) and request-scoped caching when adding new frequently-read data.
