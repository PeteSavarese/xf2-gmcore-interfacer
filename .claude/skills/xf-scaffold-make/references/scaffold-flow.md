# Scaffold Flow

## Preflight

1. Confirm target path and naming.
2. Confirm whether output belongs to core or addon.
3. Verify the command exists locally before execution.

## Execution

1. Run the smallest useful generation command.
2. Inspect generated files immediately.
3. Remove unused generated artifacts if they are not required.

## Post-Generation

1. Align generated code with existing XenForo conventions.
2. Run targeted validation on changed files.
3. Run addon sync/import workflow if addon assets were generated.
4. Skip XML release packaging unless release/build is explicitly requested.

## Creation Priority

1. First choice: `xf-make:*` command.
2. Second choice: dedicated XF command for that artifact.
3. If no make command exists: ask for clarification before manual creation.
4. Last choice: manual filesystem edit followed by explicit import.
