# xf2-gmcore-interfacer

Please refer to my [GMCore GMod repo](https://github.com/PeteSavarese/gmcore-ttt) for my notes. This is an extension of gmcore-ttt with 10 years of major iterations being performed. I am proud of where the GMCore XF is, and my full notes are shared in my GMod repo.

The XenForo web frontend for GMCore, a Garry's Mod community platform with game servers, forum, and Discord.

This repo is the forum half. It bundles:

- **GMod Interfacer** (`PeterSav/GModInterface`): the main integration. Reads the `gmcore_core` MySQL database and adds TTT stats, player history, Discord linking, and a PayPal-backed rank store to XenForo.
- **Shoutbox** (`PeterSav/Shoutbox`): a live chat that mirrors a Discord channel through the bot.
- **SteamAuth** (`BlackTea/SteamAuth`): a third-party Steam OAuth connected-account provider.
- **gl_ps_update**: the custom XenForo style.
- Docker stack (nginx, PHP-FPM 8.4, MariaDB, Flyway) for local dev and VPS deploys.

The Garry's Mod server code (Lua addons, gameserver scripts) and the Discord bot service live in separate repositories.

## Screenshots

### TTT Stats

A community-wide hub for searching players and browsing leaderboards, plus a per-player breakdown across the Innocent, Detective, and Traitor roles.

![TTT Stats hub](images/Stats%20Hub.png)

![TTT Stats per-player breakdown](images/Stats%20Player.png)

### History Logs

Search by SteamID or any historical name. See server playtime, ban / punishment / kick history, IP history, and staff notes.

![History Logs search](images/History%20Logs%20Search.png)

![History Logs player view](images/History%20Logs%20View.png)

![History Logs punishments and notes](images/History%20Logs%20Punishments%20and%20Notes.png)

### Discord Link

Link a forum account to Discord, sync ranks both ways, auto-join the guild.

![Discord Link page](images/Discord%20Link%20Page.png)

## Prerequisites

- Docker and Docker Compose v2.
- A valid XenForo 2.3 license. XenForo is proprietary commercial software and is **not distributed in this repository**. You must obtain it yourself from <https://xenforo.com/customers/>.

## Local setup

### 1. Install XenForo into `xenforo/`

1. Sign in to <https://xenforo.com/customers/> with your licensed account.
2. Download the latest XenForo 2.3 release archive.
3. Extract the **contents of the `upload/` folder** (not the folder itself) into the `xenforo/` directory of this repo.

After extraction, `xenforo/` should contain `cmd.php`, `index.php`, `admin.php`, `install.php`, `src/`, `js/`, `styles/`, and the rest of XF core, sitting alongside the bundled addon directories already in `xenforo/src/addons/` and the custom style in `xenforo/src/styles/gl_ps_update/`.

`xenforo/data/` and `xenforo/internal_data/` are gitignored. Docker creates them on first boot.

### 2. Configure environment

Edit `.env`:

- `MARIADB_*`: local DB credentials. Defaults are fine for local dev.
- `HTTP_PORT`: host port for nginx (default `8080`).
- `UID` / `GID`: set to `id -u` / `id -g` so PHP can write bind-mounted files without permission grief.
- `XF_OPTION_boardTitle`: what appears in the forum header.
- `NGINX_IMAGE` / `PHP_IMAGE`: only used by the deploy compose files. Leave as-is for local dev.

### 3. (Optional) Seed the forum DB

To populate the forum with existing data, drop a `mysqldump` of your XF database at `mariadb/gmcore_forums.sql`. The initdb script [04-import-gmcore_forums.sh](mariadb/initdb/04-import-gmcore_forums.sh) imports it into `gl_forums` on the first MariaDB boot.

Without this file, step 5 walks through a fresh XF install. See [Taking a forum DB snapshot](#taking-a-forum-db-snapshot) below for how to produce this dump.

### 4. Bring up the stack

```bash
docker compose up -d
```

This starts nginx, PHP-FPM, MariaDB, runs the Flyway migrations for `gmcore_core`, and (if present) imports the forum dump. Visit <http://localhost:8080>.

### 5. Install or upgrade XenForo

- **Fresh install** (no seed dump): open <http://localhost:8080/install> and walk through XF's installer. Use the DB credentials from `.env`. Take a SQL dump AFTER a fresh install is completed and use for seeding.
- **Imported dump**: open <http://localhost:8080/install> and run the upgrade flow. XF detects the existing schema and migrates as needed.

### 6. Install the bundled addons

```bash
docker compose exec php php cmd.php xf-addon:install BlackTea/SteamAuth
docker compose exec php php cmd.php xf-addon:install PeterSav/GModInterface
docker compose exec php php cmd.php xf-addon:install PeterSav/Shoutbox
```

### 7. Configure integrations

Each integration is wired up in the XF Admin CP:

| Integration | Where to configure | What you need |
| --- | --- | --- |
| Discord | Admin CP > Options > *GMod Interfacer - Discord* | Discord Developer App (client ID, client secret, bot token, server guild ID) |
| Steam Auth | Admin CP > Connected Accounts > *Steam* | Steam Web API key |
| PayPal store | Admin CP > Options > *GMod Interfacer - Store* | PayPal sandbox and/or production client ID and secret key |
| GMod Interfacer DB | Admin CP > Options > *GMod Interfacer* | Hostname and credentials of the `gmcore_core` MariaDB the game servers write to |

### 8. Import the custom style

The PHP container's entrypoint runs `xf-designer:import gl_ps_update` automatically in non-`local` environments. For local dev, import it manually once:

```bash
docker compose exec php php cmd.php xf-designer:import gl_ps_update
```

## Taking a forum DB snapshot

To produce `mariadb/gmcore_forums.sql` from a running XF instance:

```bash
mysqldump \
  --single-transaction \
  --default-character-set=utf8mb4 \
  --no-tablespaces \
  -h <host> -u <user> -p \
  <forum_db_name> \
  > mariadb/gmcore_forums.sql
```

A few things to know:

- `mariadb/*.sql` is gitignored. The dump holds user emails, IPs, password hashes, and PMs, so it cannot be committed. Keep it that way.
- `--single-transaction` avoids locking tables on a live forum.
- MariaDB only runs `docker-entrypoint-initdb.d/` on a fresh data volume. To re-seed an existing local environment:

  ```bash
  docker compose down
  sudo rm -rf .data/mariadb
  docker compose up -d
  ```

## Databases

| Database | Owned by | Schema managed by |
| --- | --- | --- |
| `gl_forums` (== `MARIADB_DATABASE`) | XenForo | XF install / upgrade |
| `gmcore_core` | Game servers | Flyway. See [mariadb/flyway/gl_core/](mariadb/flyway/gl_core/) |
| `gl_discord_bot` | Discord bot service (separate repo) | External |

[01-create-databases.sql](mariadb/initdb/01-create-databases.sql) and [01a-grant-permissions.sh](mariadb/initdb/01a-grant-permissions.sh) create the databases and grant `MARIADB_USER` access to all three on first boot.

## Repository layout

```text
xenforo/
├── (XenForo core, YOU install: cmd.php, src/XF, install/, styles/, …)
├── src/
│   ├── addons/
│   │   ├── BlackTea/SteamAuth/      # Steam OAuth provider (third-party)
│   │   ├── PeterSav/GModInterface/  # Main GMCore integration
│   │   └── PeterSav/Shoutbox/       # Discord-synced live chat
│   └── styles/gl_ps_update/         # Custom XenForo style
├── content/                         # Static user-facing content (music, etc.)
└── images/forum/                    # Forum images (logo, store ranks, …)

mariadb/
├── flyway/gl_core/sql/              # Flyway migrations for gmcore_core
└── initdb/                          # Bootstrap scripts (run once per fresh volume)

nginx/                               # Vhost and maintenance page
php8.4/                              # PHP-FPM 8.4 image and entrypoint
flyway/                              # Flyway image
images/                              # Screenshots for this README
.github/workflows/                   # CI / CD
docker-compose.yaml                  # Local dev
docker-compose.deploy.yaml           # Base deploy (used with a staging or prod overlay)
docker-compose.staging.yaml          # Staging overlay
docker-compose.prod.yaml             # Prod overlay
```

## Deploy

Both staging and prod run from the same deploy compose, layered with an environment-specific docker compose.

Staging:

```bash
docker compose -f docker-compose.deploy.yaml -f docker-compose.staging.yaml pull
docker compose -f docker-compose.deploy.yaml -f docker-compose.staging.yaml up -d
```

Prod:

```bash
docker compose -f docker-compose.deploy.yaml -f docker-compose.prod.yaml pull
docker compose -f docker-compose.deploy.yaml -f docker-compose.prod.yaml up -d
```

On the VPS:

1. Copy `.env.example` to `.env` and fill in real values (DB passwords, image refs, board title).
2. Use different `HTTP_PORT` values per environment so staging and prod don't collide. For example, staging on `8010` and prod on `8020`.

### GHCR image tags

Images are published under this repo's name. Example tags:

- `ghcr.io/ORGANIZATION/xf2-gmcore-interfacer-php:staging`
- `ghcr.io/ORGANIZATION/xf2-gmcore-interfacer-nginx:staging`
- `ghcr.io/ORGANIZATION/xf2-gmcore-interfacer-flyway:staging`
- ...and `:prod` variants.

The deploy compose exposes HTTP on `${HTTP_PORT:-80}`.

### Reverse proxy and vhosts

While host-level nginx vhosts are still in use, the docker stack sits behind them. Run each environment on a distinct internal port and proxy to it from the host nginx:

```nginx
server {
  server_name sandbox.example.com;

  location / {
    proxy_pass http://127.0.0.1:8010;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
  }
}

server {
  server_name example.com;

  location / {
    proxy_pass http://127.0.0.1:8020;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
  }
}
```

The container nginx trusts the Docker bridge (`172.16.0.0/12`) and restores the real client IP from the `X-Real-IP` header. See [nginx/conf.d/default.conf](nginx/conf.d/default.conf).

## Development tips

Re-import addon data after editing files in `_output/`:

```bash
docker compose exec php php cmd.php xf-dev:import --addon=PeterSav/GModInterface
```

Rebuild caches after pulling new templates or phrases:

```bash
docker compose exec php php cmd.php xf-dev:recompile
docker compose exec php php cmd.php xf-dev:rebuild-caches
```

Run XF migrations standalone:

```bash
docker compose exec php php cmd.php xf:rebuild-master-data
```

Tail logs:

```bash
docker compose logs -f php nginx
```

Xdebug ships in the PHP image. The container expects the host debugger at `host.docker.internal:9003`. See [php8.4/xdebug.ini](php8.4/xdebug.ini).
