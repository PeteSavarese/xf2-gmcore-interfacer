# Command Policy

## Artifact-to-Command Map

| Artifact type | Preferred action | Ask/Stop condition |
|---|---|---|
| Template | `xf-make:template` | If make command unavailable or context unclear |
| Phrase | `xf-make:phrase` | If phrase intent unclear |
| Entity | `xf-make:entity` | Ask before manual creation |
| Repository | `xf-make:repository` | Ask before manual creation |
| Service | `xf-make:service` | Ask before manual creation |
| Finder | `xf-make:finder` | Ask before manual creation |
| Controller | `xf-make:controller` | Ask before manual creation |
| Job | `xf-make:job` | Ask before manual creation |
| Route | `xf-make:route` | Ask before manual creation |
| Option | `xf-make:option` | Ask before manual creation |
| Cron entry | `xf-make:cron` | Ask before manual creation |
| Listener | `xf-make:listener` | Ask before manual creation |
| Class extension | `xf-make:extension` | Ask before manual creation |
| XML release artifacts (`_data/*.xml`) | Do not touch in normal development | Always ask unless task explicitly requests release/build |

## Practical Rule

When in doubt: create with `xf-make:*`, then continue from generated outputs.
