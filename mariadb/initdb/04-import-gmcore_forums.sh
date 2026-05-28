#!/bin/sh
# Take a SQL dump of a fresh install of your XenForo forums, put into dumps folder, and it will be imported
# Flyway and XF migrations will run DB migrations. You will have to go to /install to run an upgrade on a fresh
# install or XF upgrade
mariadb -u"${MARIADB_USER}" -p"${MARIADB_PASSWORD}" gl_forums < /dumps/gmcore_forums.sql