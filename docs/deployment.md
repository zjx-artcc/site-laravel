# Deployment Runbook

This document explains how the ZJX ARTCC site is built, containerized, tested, and
shipped to staging and production. It is aimed at developers and contributors who need
to understand the deploy pipeline or run the app locally.

The stack is Laravel 12 + Livewire, running on PHP 8.4 and PostgreSQL, served from a
Docker image published to GitHub Container Registry (GHCR).

## Contents

- [Docker image](#docker-image)
- [Container entrypoint](#container-entrypoint)
- [CI/CD workflows](#cicd-workflows)
- [Runtime services you must run](#runtime-services-you-must-run)
- [Caching](#caching)
- [Environment variables](#environment-variables)
- [Local development](#local-development)

---

## Docker image

The production image is defined in `Dockerfile`. It is a two-stage build on top of
`php:8.4-fpm-alpine`.

### Stage 1 — `build`

- Installs system libraries and builds/enables the PHP extensions the app needs:
  `zip`, `pdo`, `pdo_pgsql`, and `gd` (configured with freetype and jpeg support).
- Installs Node.js and npm from Alpine packages so front-end assets can be built.
- Copies Composer from the `composer:2.7.6` image.
- Copies the full source into `/var/www/html` and sets ownership to `www-data`, with
  `775` permissions on `storage` and `bootstrap/cache`.
- Installs PHP dependencies. This is gated by the `INSTALL_DEV` build argument
  (default `false`):
  - `INSTALL_DEV=true` → `composer install --prefer-dist --no-interaction`
    (includes dev dependencies).
  - default → `composer install --no-dev --prefer-dist --no-interaction`.
- Runs `npm install` followed by `npm run build` to compile the Vite front-end bundle.

### Stage 2 — production

- Starts fresh from `php:8.4-fpm-alpine` and installs only the runtime bits it needs
  (`libpq-dev`, `pdo`, `pdo_pgsql`). Node and the build toolchain are left behind.
- Copies the fully built `/var/www/html` (vendor + compiled assets) from the build
  stage.
- Copies `entrypoint.sh` in and marks it executable.
- Runs `php artisan config:clear` at build time.
- `EXPOSE 8080`.
- `CMD ["/entrypoint.sh"]`.

To build the image the way CI does (with dev dependencies for testing):

```sh
docker build --build-arg INSTALL_DEV=true -t zjx-site .
```

For a production build, omit the argument (dev dependencies are excluded by default).

---

## Container entrypoint

`entrypoint.sh` (`#!/usr/bin/env sh`, `set -eu`) runs on container start and prepares
the Laravel runtime before serving. In order it:

1. `cd /var/www/html`.
2. Creates the Laravel runtime directory tree if missing (`storage/app`,
   `storage/app/public`, `storage/framework/{cache,cache/data,sessions,testing,views}`,
   `storage/logs`, and `bootstrap/cache`). Each `mkdir` is guarded: if a path exists but
   is not a directory, it errors out.
3. Fixes permissions: if the container runs as root (`id -u` = 0) it `chown`s
   `storage` and `bootstrap/cache` to `www-data`; it always applies `chmod -R ug+rwX`
   to those paths.
4. Creates the `public/storage` symlink via `php artisan storage:link`, but only if
   `public/storage` does not already exist. If it exists as a non-symlink it is left
   untouched with a warning.
5. Optimizes caches, but **only when `LARAVEL_OPTIMIZE` is `true`** (this is the
   default when the variable is unset). When enabled it runs `optimize:clear`,
   `config:cache`, `route:cache`, and `view:cache`. This step is also gated so it only
   runs when no command is passed or when the passed command is `php artisan ...`.

**Migrations are intentionally NOT run here.** The entrypoint contains an explicit
comment that a separate migrator container owns migrations. Any deploy that changes the
schema must run `php artisan migrate` from that migrator container (or equivalent),
not from the web container.

### Serving

- With no arguments, the entrypoint runs
  `php artisan serve --host=0.0.0.0 --port=8080`.
- If arguments are passed, they are `exec`'d instead (this is how the test compose file
  overrides the command, and how a migrator/queue/scheduler container would reuse the
  same image).

---

## CI/CD workflows

All workflows live in `.github/workflows/`.

### `test.yml` — run tests in Docker

- Triggers on pull requests targeting `main`, and manually via `workflow_dispatch`.
- Builds `tests/docker-compose.yml` with `--build-arg INSTALL_DEV=true` so dev
  dependencies (Pest, Faker, etc.) are present.
- Brings the stack up, then prepares a SQLite database at
  `storage/database/database.sqlite`, generates an app key, and runs
  `php artisan migrate --seed --force`.
- Executes the suite with `./vendor/bin/pest`.
- Tears everything down with `docker compose ... down -v` (runs even on failure).

The test container (`tests/docker-compose.yml`) overrides the image's environment to a
fast, dependency-free test config: `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`,
`SESSION_DRIVER=array`. It overrides the command to run `php artisan serve` directly.

> Note: CI tests run against SQLite. This differs from the PostgreSQL used by the
> devcontainer and by production, so behavior that depends on DB-specific SQL may not be
> exercised identically.

### `lint.yml` — code style

- Triggers on push and pull request to `develop` and `main`.
- Installs dependencies, then runs Laravel Pint (`vendor/bin/pint`) as a style check.
- **Check-only:** the auto-commit step (`git-auto-commit-action`) is commented out, so
  the workflow reports style violations but does not push fixes back. Run
  `vendor/bin/pint` locally before pushing.

### `build-and-push-staging.yml` and `build-and-push-production.yml` — build & deploy

Both workflows build the image from `./Dockerfile`, push it to GHCR
(`ghcr.io/<repository>`) tagged `latest` and an `IMAGE_TAG`, then SSH into the deploy
host (`appleboy/ssh-action`) and, from the environment's `infrastructure/<env>`
directory, write an `.env` from the `ENV_FILE` secret and run
`docker compose pull && docker compose up -d`.

- **Staging:** `deploy` uses the `staging` environment and deploys under
  `infrastructure/staging`.
- **Production:** `deploy` uses the `production` environment and deploys under
  `infrastructure/production`.

> **IMPORTANT — both deploy on push to `main`.** Both the staging and the production
> workflows trigger on `push` to the `main` branch (plus manual `workflow_dispatch`).
> There is **no separate release gate for production**: merging to `main` deploys to
> staging *and* production in the same event. The only difference between them is the
> GitHub Environment used (which is where any required-reviewer / protection rules and
> per-environment secrets would apply) and the target directory on the host.

---

## Runtime services you must run

The web container only serves HTTP. Other Laravel background work runs in separate
processes/containers that reuse the same image (by passing a command that the
entrypoint `exec`s). In a full deployment you need the following alongside the web
container:

### Migrator

A container that runs `php artisan migrate` (see [entrypoint](#container-entrypoint) —
the web container never migrates).

### Queue worker

- The queue uses the `database` driver. `config/queue.php` defaults
  `QUEUE_CONNECTION` to `database`, and the default `.env` sets it to `database`.
  (Tests override this to `sync`.)
- Failed jobs and job batches are also stored in the database
  (`database-uuids` failed driver, `job_batches` / `failed_jobs` tables).
- Production requires a running worker to process queued jobs, e.g.
  `php artisan queue:work` (or `queue:listen`). Nothing in `entrypoint.sh` starts a
  worker, so run one as its own process/container.

### Scheduler

- `routes/console.php` registers two scheduled jobs:
  - `SyncRoster` — every two hours (`everyTwoHours()`).
  - `UpdateOnlineControllers` — every minute (`everyMinute()`).
- These only fire if Laravel's scheduler runs. `entrypoint.sh` does not start it, so a
  cron entry or a dedicated container must invoke `php artisan schedule:run` every
  minute (or run `php artisan schedule:work` as a long-lived process).

---

## Caching

- The application cache uses the `database` store (`CACHE_STORE=database`), so cached
  values live in the database rather than in Redis/Memcached.
- Laravel's config/route/view caches are built at container start by
  `entrypoint.sh` when `LARAVEL_OPTIMIZE` is true (the default). If you change config,
  routes, or Blade views, a container restart (or manually re-running the cache
  commands) is what picks them up.

---

## Environment variables

The canonical starting point is `.env.example`. **Never commit real values** — the list
below is variable **names** only, grouped by concern. Some variables are read by config
files (`config/app.php`, `config/logging.php`, `config/mail.php`) but are not present in
`.env.example`; those are noted.

### Application

- `APP_NAME` — display name of the site.
- `APP_ENV` — environment (`local`, `testing`, `production`).
- `APP_KEY` — Laravel encryption key.
- `APP_DEBUG` — debug mode toggle.
- `APP_URL` — base URL of the site.
- `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` — localization.
- `APP_MAINTENANCE_DRIVER` (and `APP_MAINTENANCE_STORE`) — maintenance-mode backend.
- `PHP_CLI_SERVER_WORKERS` — worker count for the built-in server.
- `BCRYPT_ROUNDS` — password hashing cost.
- `LARAVEL_OPTIMIZE` — (consumed by `entrypoint.sh`, not in `.env.example`) toggles the
  cache-building step at container start; defaults to `true` when unset.

### Logging

- `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL` — log routing and
  verbosity. Run production at `LOG_LEVEL=info`; sync jobs emit their completion
  summaries at that level. Drop to `debug` temporarily to investigate a sync — see
  [logging](architecture.md#logging).
- Note `LOG_STACK` is comma-separated and defaults to `single`. The `discord` channel
  only receives anything if it is listed there (e.g. `LOG_STACK=daily,discord`).

### Database

- `DB_CONNECTION` — database driver (`pgsql` in the default env).
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — connection.
- `DB_QUEUE_CONNECTION` — connection used by the database queue.
- `DB_CACHE_CONNECTION` — connection used by the database cache store.

### Sessions

- `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`,
  `SESSION_DOMAIN`.

### Cache / queue / broadcast / filesystem

- `CACHE_STORE` — cache backend (`database`).
- `QUEUE_CONNECTION` — queue backend (`database`).
- `BROADCAST_CONNECTION` — broadcast driver.
- `FILESYSTEM_DISK` — default storage disk.

### Redis / Memcached (present but not the default backends)

- `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`.
- `MEMCACHED_HOST`.

### Mail (ZeptoMail is delivered over SMTP via these vars)

- `MAIL_MAILER` — mailer/transport.
- `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` — SMTP
  connection (point host/port/credentials at ZeptoMail for production email).
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` — default sender.

### AWS / S3

- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`,
  `AWS_USE_PATH_STYLE_ENDPOINT`.

### Front-end (Vite)

- `VITE_APP_NAME` — app name exposed to the front-end build.

### VATSIM OAuth (Connect / SSO login)

- `VATSIM_AUTH_URL` — VATSIM Connect OAuth base URL.
- `VATSIM_CLIENT_ID` — OAuth client ID.
- `VATSIM_CLIENT_SECRET` — OAuth client secret.
- `VATSIM_API_URL` — (read by `config/app.php`, not in `.env.example`) VATSIM API base
  URL.

### VATUSA API

- `VATUSA_FACILITY` — facility identifier (`ZJX`).
- `VATUSA_API_URL` — VATUSA API base URL.
- `VATUSA_API_KEY` — VATUSA API key.

### Discord webhooks

- `TRAINING_REQUEST_WEBHOOK_URL` — webhook the app posts training requests to (read by
  `config/app.php`).
- `LOG_DISCORD_WEBHOOK_URL` and `LOG_DISCORD_IGNORE_EXCEPTIONS` — (read by
  `config/logging.php` for the Discord log channel, not in `.env.example`) webhook and
  exception-ignore toggle for the Discord logging channel.

---

## Local development

The devcontainer (`.devcontainer/`) provides a ready-to-use environment:

- Built on `mcr.microsoft.com/devcontainers/php:1-8.4-bookworm` with `pdo_pgsql`,
  `pgsql`, and `zip` extensions plus Node.js/npm and the Postgres client.
- `docker-compose.yml` runs the app service alongside a `postgres:18` `db` service.
- Forwards ports `8000` (serve), `5173` (Vite), and `5432` (Postgres).
- `postCreateCommand` runs `composer install && npm install` on creation.

Once dependencies are installed, run everything at once with:

```sh
composer run dev
```

Defined in `composer.json`, this uses `concurrently` to launch four processes together
(with `--kill-others`):

- `php artisan serve` — the HTTP server (`server`).
- `php artisan queue:listen --tries=1` — the queue worker (`queue`).
- `php artisan pail --timeout=0` — live log tailing (`logs`).
- `npm run dev` — the Vite dev server with HMR (`vite`).

There is also `composer run dev:ssr` for the SSR variant, and `composer run setup`
(install deps, copy `.env`, generate key, migrate, build assets) to bootstrap a fresh
checkout.

> The scheduler is **not** part of `composer run dev`. If you need to exercise the
> scheduled jobs (`SyncRoster`, `UpdateOnlineControllers`) locally, run
> `php artisan schedule:work` in a separate terminal.
