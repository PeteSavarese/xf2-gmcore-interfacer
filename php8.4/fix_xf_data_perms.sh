#!/bin/sh
set -e

mkdir -p /var/www/html/data /var/www/html/internal_data

# Write install-lock to prevent XF thinking this is a fresh install
if [ ! -f /var/www/html/internal_data/install-lock.php ]; then
	echo "Creating install-lock.php..."
	echo '<?php header("Location: ../index.php"); exit;' > /var/www/html/internal_data/install-lock.php
fi

# For local dev, skip chown since we run as host user
# For prod/staging, the volumes are owned by the container user already
if [ "${ENVIRONMENT:-prod}" != "local" ]; then
	chown -R www-data:www-data /var/www/html 2>/dev/null || true
fi

chmod -R 755 /var/www/html 2>/dev/null || true
chmod -R 777 /var/www/html/data /var/www/html/internal_data 2>/dev/null || true

if [ -f /var/www/html/cmd.php ]; then
	echo "Setting GMCore XF options from env variables..."
	php /var/www/html/cmd.php gmcore:set-options --env || echo "No XF_OPTION_* variables found, skipping..."

	# Create flag file to trigger maintenance page
	echo "Rebuilding XenForo templates and caches..."
	touch /var/www/html/data/.rebuilding

	XF_DEVELOPMENT_ENABLED=1 php /var/www/html/cmd.php xf:rebuild-master-data
	XF_DEVELOPMENT_ENABLED=1 php /var/www/html/cmd.php xf-dev:recompile
	XF_DEVELOPMENT_ENABLED=1 php /var/www/html/cmd.php xf-dev:analyze-icons
	XF_DEVELOPMENT_ENABLED=1 php /var/www/html/cmd.php xf-dev:rebuild-caches

	# God bless this. We have to fetch the style id first via xf_style
	# enable designer on style id, mport gl_ps_update style,
	# then disable designer mode. There has to be a beter way to do this
	echo "Importing gl_ps_update style from filesystem..."
	STYLE_ID=$(php -r "
		\$config = [];
		require '/var/www/html/src/config.php';
		try {
			\$pdo = new PDO(
				'mysql:host=' . \$config['db']['host'] . ';dbname=' . \$config['db']['dbname'],
				\$config['db']['username'], \$config['db']['password']
			);
			\$row = \$pdo->query(\"SELECT style_id FROM xf_style WHERE title = 'GMCore' LIMIT 1\")->fetch(PDO::FETCH_ASSOC);
			echo \$row ? \$row['style_id'] : '';
		} catch (Exception \$e) {}
	" 2>/dev/null)
	if [ -n "$STYLE_ID" ]; then
		echo "Found gl_ps_update style (id=$STYLE_ID), importing..."

		XF_DESIGNER_ENABLED=1 php /var/www/html/cmd.php xf-designer:enable "$STYLE_ID" gl_ps_update || true
		XF_DESIGNER_ENABLED=1 php /var/www/html/cmd.php xf-designer:import gl_ps_update || true
		XF_DESIGNER_ENABLED=1 php /var/www/html/cmd.php xf-designer:disable gl_ps_update || true

		echo "Style import complete."
	else
		echo "Could not find gl_ps_update style in DB (designer_mode_id not set). Skipping style import."
	fi

	# Remove flag file
	rm -f /var/www/html/data/.rebuilding

	chown -R www-data:www-data /var/www/html/data /var/www/html/internal_data 2>/dev/null || true
fi

exec "$@"