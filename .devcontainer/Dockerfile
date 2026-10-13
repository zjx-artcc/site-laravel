FROM mcr.microsoft.com/devcontainers/php:1-8.4-bookworm

# Some upstream devcontainer variants include a Yarn apt source without a valid key.
# Remove it to keep apt update deterministic.
RUN find /etc/apt/sources.list.d -maxdepth 1 -type f -iname "*yarn*" -delete

# System libraries + PHP extensions. gd mirrors the production Dockerfile.
# Node is provided by the devcontainers/features/node feature, not apt.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev postgresql-client libzip-dev zip \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql zip gd pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# The base image ships Xdebug in step-debug mode on every request (port 9000),
# which spams "Could not connect" warnings during `composer run dev`. Switch it to
# trigger-only on the Xdebug 3 default port so debugging still works when your IDE
# asks for it, without noise the rest of the time. Loaded after the base xdebug.ini.
COPY .devcontainer/xdebug-overrides.ini /usr/local/etc/php/conf.d/zz-xdebug-overrides.ini

USER vscode
