# deploy/Dockerfile

# stage 1: build stage
FROM php:8.4-fpm-alpine as build

# installing system dependencies and php extensions
RUN apk add --no-cache \
    libpq-dev \
    zip \
    libzip-dev \
    freetype \
    libjpeg-turbo \
    libpng \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libpq-dev \
    nodejs \
    npm \
    git \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip pdo pdo_pgsql \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-enable gd

# install composer
COPY --from=composer:2.7.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
ARG INSTALL_DEV=false

# copy necessary files and change permissions
COPY . .
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# install php and node.js dependencies
RUN if [ "$INSTALL_DEV" = "true" ]; then \
      composer install --prefer-dist --no-interaction; \
    else \
      composer install --no-dev --prefer-dist --no-interaction; \
    fi \
    && npm install \
    && npm run build

RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 775 /var/www/html/

# stage 2: production stage
FROM php:8.4-fpm-alpine

RUN apk add --no-cache ca-certificates curl libcurl libpq-dev freetype libjpeg-turbo libpng \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-enable gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# copy files from the build stage
COPY --from=build /var/www/html /var/www/html

WORKDIR /var/www/html

# Without this the repo's php.ini is just a file in the app directory and PHP's
# 2M upload_max_filesize default applies, which rejects event banners before
# Laravel's validator ever sees them.
COPY ./php.ini "$PHP_INI_DIR/conf.d/zzz-app.ini"

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Include the public-disk symlink in the image as well as verifying it at
# startup. Some deployment platforms override CMD, which would otherwise skip
# entrypoint.sh and leave uploads inaccessible at /storage/....
RUN ["php", "artisan", "storage:link"]
RUN ["php", "artisan", "config:clear"]
EXPOSE 8080
CMD ["/entrypoint.sh"]
