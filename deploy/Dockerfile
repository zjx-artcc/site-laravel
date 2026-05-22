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
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip pdo pdo_pgsql \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-enable gd

# install composer
COPY --from=composer:2.7.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# copy necessary files and change permissions
COPY . .
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# install php and node.js dependencies
RUN composer install --no-dev --prefer-dist \
    && npm install \
    && npm run build

RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 775 /var/www/html/

# stage 2: production stage
FROM php:8.4-fpm-alpine

RUN apk add --no-cache ca-certificates curl libcurl libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# copy files from the build stage
COPY --from=build /var/www/html /var/www/html
COPY ./deploy/nginx.conf /etc/nginx/http.d/default.conf
COPY ./deploy/php.ini "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html

COPY ./deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["php", "artisan", "config:clear"]
CMD ["/entrypoint.sh"]
