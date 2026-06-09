---
name: xf-scaffold-make
description: XenForo command-driven scaffolding and code generation workflow. Use when creating new structures with xf-make commands, generating boilerplate, or selecting safe generation steps before manual refinement.
---

# XF Scaffold and Make Commands

Read [shared policy](../xf-shared-policy/SKILL.md) for universal rules (runner detection, artifact boundaries, import/export, phrase conventions).

## Workflow

1. Confirm target area: core or specific addon.
2. Discover available commands from local command listing (`xf list` or `php cmd.php list`).
3. Run the minimum `xf-make:*` command set needed.
4. Review generated output and refine manually to fit local patterns.
5. Avoid over-generation that introduces unused artifacts.

## Guardrails

1. Prefer generation for repetitive boilerplate, not business logic.
2. Validate names/paths before running commands.
3. If files were edited manually in `_output/`, import before any export.
4. Re-run sync/import steps only when required by workflow.

## Non-Interactive Usage

1. All `xf-make:*` commands support non-interactive execution by passing options on the command line.
2. Run `<command> --help` to discover available options before execution.
3. Always prefer non-interactive invocation to avoid stdin issues in automated environments.

## References

Read [references/scaffold-flow.md](references/scaffold-flow.md) for the command selection checklist.
Read [references/command-policy.md](references/command-policy.md) for the artifact-to-command mapping.
