# vZJX ARTCC Website

The web application for the virtual Jacksonville ARTCC (vZJX) on the VATSIM network. It manages the controller roster, training, events, visiting-controller requests, certifications, and related facility operations, and integrates tightly with VATSIM Connect (login) and the VATUSA API (roster, training records, solo certifications).

## Stack

- **Laravel 12** (PHP 8.2+) with **Livewire** (class-based components) for interactive UI
- **Tailwind CSS v4 + daisyUI** for styling, built with **Vite**
- **PostgreSQL** in development and production (see [Testing](#testing) for the CI caveat)
- **Pest 4** for tests
- Authentication via **VATSIM Connect OAuth** (no local passwords); authorization via **spatie/laravel-permission**
- Background work on Laravel's **database** queue; roster, live-controller, and historical-statistics data synced on a schedule

## Architecture at a glance

- All routes live in `routes/web.php` (there is no `routes/api.php`). Scheduled tasks are in `routes/console.php`.
- Business logic that talks to external services runs in **jobs** (`app/Jobs/`) and **services** (`app/Services/`); external API responses are parsed into **DTOs** (`app/DTOs/`).
- Domain models are in `app/Models/`, enums in `app/Enums/`, transactional email in `app/Mail/`.
- Authorization is enforced by Spatie role/permission **middleware** on route groups (registered in `bootstrap/app.php`) plus some inline checks — there are no Policies or Gates.

For the full picture, start with [`docs/architecture.md`](docs/architecture.md).

## Documentation

| Area | Doc |
| --- | --- |
| Architecture & directory layout | [`docs/architecture.md`](docs/architecture.md) |
| Authentication & authorization | [`docs/authentication-authorization.md`](docs/authentication-authorization.md) |
| Database schema | [`docs/database.md`](docs/database.md) |
| VATSIM / VATUSA integration | [`docs/vatsim-integration.md`](docs/vatsim-integration.md) |
| Deployment & CI/CD | [`docs/deployment.md`](docs/deployment.md) |
| Roster & membership | [`docs/systems/roster-and-membership.md`](docs/systems/roster-and-membership.md) |
| Training (tickets, assignments, solo certs) | [`docs/systems/training.md`](docs/systems/training.md) |
| Events | [`docs/systems/events.md`](docs/systems/events.md) |
| Visiting controllers | [`docs/systems/visiting-controllers.md`](docs/systems/visiting-controllers.md) |
| Certifications | [`docs/systems/certifications.md`](docs/systems/certifications.md) |
| Users & profiles | [`docs/systems/users-and-profiles.md`](docs/systems/users-and-profiles.md) |
| Audit logging | [`docs/systems/audit-logging.md`](docs/systems/audit-logging.md) |
| Known issues & follow-ups | [`docs/discrepancies.md`](docs/discrepancies.md) |

## Getting started

```bash
git clone git@github.com:zjx-artcc/site-laravel.git
cd site-laravel

composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure PostgreSQL and VATSIM OAuth in your `.env`, then:

```bash
php artisan migrate --seed
```

Seeding sets up permissions and queues a roster sync (which needs VATUSA API access — see [`CONTRIBUTING.md`](CONTRIBUTING.md)).

To run the app, prefer `composer run dev` over `php artisan serve` — it runs the web server, queue worker, log tailer, and Vite together:

```bash
composer run dev
```

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for full local-setup details, including how to obtain environment secrets.

## Testing

```bash
php artisan test
```

Locally, tests run against PostgreSQL (`phpunit.xml` sets `DB_CONNECTION=pgsql`, database `zjx_test`). CI runs the same suite against SQLite inside Docker. This split is a known inconsistency — see the [testing notes in `CONTRIBUTING.md`](CONTRIBUTING.md#testing) and [`docs/discrepancies.md`](docs/discrepancies.md).

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for local setup, branching, pull requests, coding standards, testing, and the review process.

## Security

Do not report security vulnerabilities through public issues. See [`SECURITY.md`](SECURITY.md) for reporting instructions and security practices.

## License

This project is licensed under GPLv3. See the [`LICENSE`](LICENSE) file for details.
