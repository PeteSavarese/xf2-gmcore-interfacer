FROM php:8.4-fpm

ARG ENVIRONMENT=production

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      unzip zlib1g-dev libzip-dev libgmp-dev \
      libfreetype6-dev \
      libjpeg62-turbo-dev \
      libpng-dev \
      libjpeg-dev \
      libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo_mysql zip bcmath exif \
    && docker-php-ext-configure gmp && docker-php-ext-install gmp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j "$(nproc)" gd

RUN curl -L --output /usr/local/bin/pickle \
      https://github.com/FriendsOfPHP/pickle/releases/download/v0.7.11/pickle.phar \
    && chmod +x /usr/local/bin/pickle \
    && pickle install imagick && docker-php-ext-enable imagick \
    && pickle install redis && docker-php-ext-enable redis

RUN if [ "$ENVIRONMENT" = "local" ]; then \
    pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

COPY --from=composer:2.9.4 /usr/bin/composer /usr/bin/composer

COPY php8.4/xdebug.ini /tmp/xdebug.ini
RUN if [ "$ENVIRONMENT" = "local" ]; then \
    cp /tmp/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini; \
    fi

COPY php8.4/zz-logging.conf /usr/local/etc/php-fpm.d/zz-logging.conf

COPY php8.4/fix_xf_data_perms.sh /fix_xf_data_perms.sh
RUN sed -i 's/\r$//' /fix_xf_data_perms.sh && chmod +x /fix_xf_data_perms.sh

ENTRYPOINT ["/fix_xf_data_perms.sh"]
CMD ["php-fpm"]

WORKDIR "/var/www/html"
COPY xenforo/ /var/www/html/
RUN rm -rf /var/www/html/scripts
