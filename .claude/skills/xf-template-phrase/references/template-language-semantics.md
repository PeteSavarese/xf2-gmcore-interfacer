# XF Template Language Semantics

Prevent syntax hallucinations and false-positive fixes when editing XenForo templates.

## Core Syntax Categories

### 1. Template tags (compile-time structures)

Examples: `<xf:form>...</xf:form>`, `<xf:if>...</xf:if>`, self-closing `<xf:fa icon="fa-user" />`.
Treat as template-tag syntax, not HTML-only semantics.

### 2. Template expressions (double braces)

Use `{{ ... }}` for expression evaluation: `{{ $var ?? false }}`.
Do not rewrite valid double-brace expressions into single-brace forms.

### 3. Variable interpolation (single braces)

Use `{$var}` in supported contexts: `<xf:title>{$title}</xf:title>`.
Do not assume single-brace interpolation is invalid.

### 4. Expression-capable attributes

Some tag attributes are expression contexts and do not need `{{ ... }}` wrappers.
Example: `<xf:if is="$var ?? false">`.
Do not force brace-wrapping inside these attribute contexts.

### 5. Whitespace and indentation

XF template semantics are not indentation-sensitive.
Do not flag `<xf:else />` as invalid because of indentation or formatting.

## Runtime Helpers

- **Template functions**: Runtime calls in expression contexts, e.g. `{{ phrase('a_phrase') }}`.
- **Template filters**: Filter syntax works in both interpolation and expression contexts:
  - Single-brace: `{$title|to_upper}`
  - Double-brace: `{{ $title|to_upper }}`

  Preserve existing filter chains.

## Rules

1. Do not auto-convert between `{{ ... }}` and `{$...}` forms without a context-specific reason.
2. Do not treat formatting choices as syntax errors.
3. Do not invent template-lint rules that are not part of XenForo template semantics.
4. Prefer matching the surrounding file's existing syntax style.
5. If unsure whether a construct is valid, inspect nearby core examples before editing.
