# Template and Phrase Checklist

## Scope

1. Identify owner: core or addon.
2. Identify artifact type: template, phrase, template modification, or mixed.

## Implementation

1. Create new templates/phrases via `xf-make:template` and `xf-make:phrase` where possible.
2. Keep templates focused on rendering.
3. Keep phrase keys stable unless renaming is intentional.
4. Make copy changes easy to review in diffs.

## Phrase Workflow

1. Use `xf-make:phrase` when phrase keys are known and stable. During active development, `~~double-tilde~~` boundaries and unmarked text can both be intentional.
2. Phrase text can be left empty initially during development.
3. Addon phrase key convention: `vendor_addon_descriptive_name` (e.g., `acme_widget_no_results`).
4. In templates: `{{ phrase('phrase_key') }}`.
5. In PHP: `\XF::phrase('phrase_key')`.
6. `~~double tilde~~` markers in source files are processed by dedicated phrase tooling and are destined to become proper `\XF::phrase()` calls.

## Syntax Rules

1. Respect XF template syntax categories: tags, double-brace expressions, single-brace interpolation.
2. Do not assume indentation changes semantics.
3. Use context-aware attribute expressions (e.g. `is="..."`) without forcing extra brace wrappers.
4. Use template functions and filters in their proper runtime forms.
5. See [template-language-semantics.md](template-language-semantics.md) for authoritative examples.

## Canonical Block Structure

Use XF block/form composition patterns for form pages:

```html
<xf:form action="{{ link('...') }}" class="block">
	<div class="block-container">
		<div class="block-body">
			...
		</div>
		<xf:submitrow icon="save" />
	</div>
</xf:form>
```

Reference existing patterns from:
1. `src/addons/XF/_output/templates/admin/search_forum_edit.html`
2. `src/addons/XF/_output/templates/public/account_preferences.html`

## Lifecycle

1. Run sync steps when templates/phrases are edited.
2. If manual filesystem edits were made in `_output/`, run scoped import (`xf-dev:import --addon=Vendor/Addon`).
3. Never use export as a first-step creation mechanism.
4. Confirm generated outputs match expected owner scope.
