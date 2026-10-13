# Contributing

Thanks for your interest in contributing to this project. Contributions are welcome and appreciated.

This guide explains how to set up the project, make changes, and submit contributions.

## Getting Started

### 1. Clone the repository

```bash
git clone <repository-url>
cd <project-name>
```

### 2. Install PHP dependencies

This project uses Laravel. Install PHP dependencies with Composer:

```bash
composer install
```

### 3. Install frontend dependencies

If the project uses frontend assets, install JavaScript dependencies:

```bash
npm install
```

### 4. Configure your environment

Copy the example environment file:

```bash
cp .env.example .env
```

Generate an application key:

```bash
php artisan key:generate
```

Update your `.env` file with your local configuration.

This project uses PostgreSQL. Make sure your local database settings are configured correctly:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=<database-name>
DB_USERNAME=<database-user>
DB_PASSWORD=<database-password>
```

Configure VATSIM OAuth test credentials:
See the wiki at `https://vatsim.dev/services/connect/` in how to do this. Common issues with authentication are usually related to the redirect URL not exactly matching the URL in your browser.

### Other Environment Secrets

Some features may require specific environment secrets or service credentials that are not included in the repository.

If you need access to specific environment secrets, contact:

```text
zjx-wm@vatusa.net
```

Do not commit secrets, credentials, tokens, API keys, or private configuration values to the repository.

### 5. Run database migrations

After configuring PostgreSQL, run the migrations:

```bash
php artisan migrate
```

And seed the database, which will seed the permissions information and queue a roster sync job (if the sync job fails, make sure you have access to the VATUSA API and have the base URL set in your .env).
```bash
php artisan db:seed
```

### 6. Start the development server
It is recommended to use `composer run dev` instead of `php artisan serve` as it will run the queue worker and scheduler concurrently, as well as bundle assets required by npm.
```bash
composer run dev
```

## Selecting an Issue for Work
An open issue cannot be selected or claimed unless it has the tag "approved by WM." Do not claim an issue for work if someone else has assigned it to themselves. 

## Making Changes

Please keep changes focused and easy to review.

Before opening a pull request:

* Make sure your code follows the existing Laravel conventions.
* Add or update tests when appropriate.
* Update documentation if your change affects setup, usage, or behavior.
* Avoid unrelated formatting or refactoring in the same pull request.
* Do not commit generated files, local environment files, or dependency directories unless specifically required.

## Testing

Run the test suite before submitting your changes:

```bash
php artisan test
```

You can also run Pest directly (`./vendor/bin/pest`) or via the Composer script, which clears cached config first:

```bash
composer test
```

### Conventions

- Tests use [Pest](https://pestphp.com/). The base `Tests\TestCase` and the `RefreshDatabase` trait are bound to the **Feature** suite in `tests/Pest.php`, so each Feature test runs against a fresh, migrated database.
- Model data is created with factories and `spatie/laravel-permission` helpers, e.g. `User::factory()->create()` and `$user->assignRole('staff')`. Only `UserFactory` exists today — if you test a model that has no factory, add one under `database/factories/`.
- Authorization tests seed permissions first with `$this->seed(PermissionSeeder::class)` before assigning roles.
- Custom expectations live in `tests/Pest.php` (e.g. `toRunInLessThan(...)` for performance-sensitive assertions).

### Database engine caveat

There is a known split between local and CI test databases:

- **Locally**, `phpunit.xml` sets `DB_CONNECTION=pgsql` with database `zjx_test`, so `php artisan test` runs against PostgreSQL. Create that database (or adjust `phpunit.xml`) before running tests.
- **In CI**, tests run against SQLite inside Docker (`tests/docker-compose.yml` overrides the connection). The container's environment variable takes precedence over `phpunit.xml`.

Because the two environments use different engines, prefer database-agnostic queries and be cautious with engine-specific SQL. This inconsistency is tracked in [`docs/discrepancies.md`](docs/discrepancies.md).

## Database Changes

If your contribution changes the database schema:

* Create a Laravel migration.
* Avoid modifying existing migrations unless specifically requested.
* Include any needed model, factory, seeder, or test updates.
* Make sure migrations run cleanly on a fresh PostgreSQL database.

## Commit Guidelines

Use clear, descriptive commit messages.

Good examples:

```text
Add user profile validation
Fix PostgreSQL migration issue
Update Laravel queue configuration
```

Avoid vague messages such as:

```text
fix stuff
updates
changes
```

## Pull Requests

When opening a pull request, please include:

```markdown
## Summary

Briefly describe what changed.

## Why

Explain why this change is needed.

## Testing

Describe how you tested the change.
```
PR Authors are responsible for resolving merge conflicts. If something is ambiguous, please do not commit any changes and consult a PR reviewer with what was ambiguous for clarification.

For user-facing changes, include screenshots or examples when helpful.

## Code Style

Follow the style and conventions already used in the project.

For Laravel code:

* Use Laravel conventions for controllers, models, requests, policies, jobs, events, listeners, and services.
* Prefer clear, readable code over overly clever solutions.
* Keep business logic out of routes when possible.
* Use migrations for database schema changes.
* Use environment variables for configuration values.

## Reporting Bugs

When reporting a bug, please include:

* A clear description of the problem.
* Steps to reproduce the issue.
* What you expected to happen.
* What actually happened.
* Your environment, such as operating system, PHP version, PostgreSQL version, Node version, and browser.
* Screenshots, logs, or error messages when helpful.

## Suggesting Features

Feature suggestions are welcome.

Please describe:

* The problem the feature would solve.
* The proposed solution.
* Any alternatives you considered.
* Whether you are willing to help implement it.

Please create an issue for any relevant features and discussion.

## Security Issues

Please do not open a public issue for security vulnerabilities.

Instead, report security issues privately to:

```text
zjx-wm@vatusa.net
```

Please see SECURITY.md for more information.

## Code of Conduct

Be respectful and constructive.

We want this project to be welcoming to contributors of all experience levels. Harassment, discrimination, or abusive behavior will not be tolerated.

## License

By contributing to this project, you agree that your contributions will be licensed under the GPLv3 License.
