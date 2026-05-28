---
name: xf-template-phrase
description: XenForo template and phrase workflow for safe UI/content changes. Use when editing templates, phrases, template modifications, or style-related output.
---

# XF Template and Phrase Workflow

Read [shared policy](../xf-shared-policy/SKILL.md) for universal rules (runner detection, artifact boundaries, import/export, phrase conventions).

## Workflow

1. Confirm ownership: core template/phrase or addon-owned.
2. Use `xf-make:template` / `xf-make:phrase` for new artifacts.
3. Implement the smallest template/phrase change that satisfies the request.
4. Keep logic out of templates unless an established pattern requires it.
5. Run scoped sync/import steps for the touched owner scope.

## Guardrails

1. Do not hand-edit files that should be generated from sync/import workflows.
2. Preserve phrase/template naming conventions.
3. Keep user-facing copy changes explicit and reviewable.
4. Use `<xf:fa icon="..." />` in templates for Font Awesome icons. Do not insert raw `<i class="fa ...">` tags.
5. For LESS-driven icon styling, follow existing XF mixins and variables (`.m-faContent`, `.m-faBefore`, `@fa-var-*`).

## References

Read [references/template-phrase-checklist.md](references/template-phrase-checklist.md) before finalizing template/phrase changes.
Read [references/template-language-semantics.md](references/template-language-semantics.md) when editing template syntax.
