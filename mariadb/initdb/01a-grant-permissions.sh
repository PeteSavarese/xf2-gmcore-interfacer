#!/bin/sh
# Create forums user and grant permissions
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" <<-EOSQL
    CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';

    GRANT ALL PRIVILEGES ON gmcore_core.* TO '${MARIADB_USER}'@'%';
    GRANT ALL PRIVILEGES ON gmcore_forums.* TO '${MARIADB_USER}'@'%';
    GRANT ALL PRIVILEGES ON gmcore_discord_bot.* TO '${MARIADB_USER}'@'%';

    FLUSH PRIVILEGES;
EOSQL
