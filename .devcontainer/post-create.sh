#!/usr/bin/env bash
# Idempotent bootstrap for the dev container. Safe to re-run.
set -euo pipefail

# Move to the repo root regardless of where this is invoked from.
cd "$(dirname "$0")/.."

echo "==> Ensuring .env exists"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "    created .env from .env.example"
else
  echo "    .env already present, leaving untouched"
fi

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist

echo "==> Ensuring APP_KEY is set"
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate
else
  echo "    APP_KEY already set, leaving untouched"
fi

echo "==> Installing JavaScript dependencies"
npm install

echo "==> Waiting for PostgreSQL to be ready"
until pg_isready -h db -p 5432 -U postgres >/dev/null 2>&1; do
  echo "    waiting for Postgres..."
  sleep 1
done
echo "    Postgres is ready"

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Linking storage"
php artisan storage:link || true

echo "==> Dev container ready. Run 'composer run dev' to start the app + Vite."
