# CLAUDE.md

This file provides AI agent guidance for XenForo development.

## Project Overview

XenForo is a full-stack PHP/JavaScript community platform. This project may contain the core framework and add-ons.

### Technical Stack

- **Framework**: XenForo 2.3 (proprietary PHP forum software)
- **PHP**: 7.2+
- **Database**: MySQL/MariaDB with utf8mb4
- **Frontend**: Vanilla JS (ES2020), LESS for styling
- **Testing**: PHPUnit, PHPStan
- **Code Quality**: PHP-CS-Fixer, PHPLint, ESLint

## Important Notes

- Core XF edits may be allowed, though usually not if developing add-ons.
- `src/vendor/` is not version-controlled and should not be modified directly.
- Follow existing XF patterns for consistency with the framework.
- Keep changes minimal and backwards compatible.
- Rely on self-documenting code. Avoid comments that are redundant with the code itself.

## Architecture

```
src/
├── XF/                        # Core XenForo framework
│   ├── Entity/                # Data models, column definitions, relations
│   ├── Repository/            # Data access logic, complex queries
│   ├── Service/               # Business logic, multi-step operations
│   ├── Finder/                # Chainable query builders
│   ├── Pub/Controller/        # Public-facing controllers
│   ├── Admin/Controller/      # Admin panel controllers
│   ├── Api/Controller/        # API controllers
│   ├── Install/               # Install and upgrade logic
│   └── Job/                   # Background job processing
├── addons/                    # Addons (XF, XFES, XFMG, XFRM, etc.)
│   └── <Vendor>/<Addon>/
│       ├── addon.json         # Metadata and version
│       ├── Setup.php          # Install/upgrade logic
│       ├── _output/           # Development output (templates, phrases, etc.)
│       └── _data/             # XML release artifacts (do not edit directly)
├── tests/                     # PHPUnit tests
└── vendor/                    # Composer dependencies (not version-controlled)
js/
├── xf/                        # Core JavaScript
└── vendor/                    # Third-party JS libraries
```

### Key Patterns

**Entity-Repository-Service**:
- Entities define data structure, column types, and relations.
- Repositories handle complex queries and data access.
- Services encapsulate business logic and multi-step operations.
- Finders build chainable queries.
- Controllers stay thin — orchestrate services and return views.

**Class Extensions** (for addons extending core):
- Defined in `_output/class_extensions/`.
- Applied automatically by the class extension system.

**Template System**:
- Development templates live in `_output/templates/`.
- Exported templates in `_data/templates/` are release artifacts — do not edit directly.
- Template modifications in `_data/template_modifications/` — do not edit directly.
- XenForo templates use their own tag syntax (`<xf:if>`, `<xf:form>`, `<xf:fa>`), double-brace expressions (`{{ ... }}`), and single-brace interpolation (`{$var}`).

**JavaScript** (script-include pattern, no bundler):
- Core JS lives in `js/xf/`.
- Addon JS lives in `js/<vendor>/<addon>/`.
- Addons can also serve JS via a `_files/` directory in the addon folder with a `build.json` that copies files to the `js/` directory on build.

### Database Queries

Use XenForo's query builder:

```php
// Via Finder
$finder = \XF::finder(\XF\Entity\Thread::class);
$threads = $finder->where('discussion_open', 1)->fetch();

// Via Entity relations
$thread = \XF::em()->find(\XF\Entity\Thread::class, $threadId);
$forum = $thread->Forum;
```

## Essential Commands

### Addon Lifecycle

```bash
php cmd.php xf-addon:create                      # Create a new addon
php cmd.php xf-addon:bump-version                # Bump add-on version
php cmd.php xf-addon:build-release				 # Build add-on release zip (handles export of add-on _data XML)
php cmd.php xf-addon:install Vendor/Addon        # Install an addon
php cmd.php xf-addon:upgrade Vendor/Addon        # Upgrade an addon
php cmd.php xf-dev:import --addon=Vendor/Addon   # Import addon data from _output/ files
```

> **Note**: If `compose.yaml` exists at the repo root, use the `xf` wrapper instead of `php cmd.php`. For example: `xf xf-addon:install Vendor/Addon`. See the `xf-shared-policy` skill for full runner detection rules. Do not run `xf-dev:export` manually in normal development.

## Skills

This repository includes development skills in `.claude/skills`. Load the relevant skills for your current task:

| Skill | When to use |
|---|---|
| `xf-shared-policy` | Always — universal rules for runner detection, artifact boundaries, import/export, phrases |
| `xf-core-dev` | Changes to `src/XF/`, `js/xf/`, `src/addons/XF/` |
| `xf-addon-dev` | Changes to `src/addons/` outside `src/addons/XF/` |
| `xf-scaffold-make` | Creating new artifacts with `xf-make:*` commands |
| `xf-template-phrase` | Editing templates, phrases, template modifications |

Load only the skills relevant to your task. Multiple skills can be combined when work spans areas.
