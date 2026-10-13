# AGENTS.md

Orientation and operating rules for AI coding agents working in this repository. This
file is deliberately short — it points at the real documentation rather than
duplicating it, and records conventions/gotchas that aren't obvious from reading the
code once.

## Start here

- [`README.md`](README.md) — stack summary and the full documentation index (architecture,
  auth, database, VATSIM integration, deployment, and one doc per subsystem under `docs/systems/`).
- [`docs/architecture.md`](docs/architecture.md) — directory layout, request lifecycle, routing,
  scheduled work, infrastructure drivers. Read this before making structural changes.
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — local setup, PR process, commit/PR conventions.
- [`docs/discrepancies.md`](docs/discrepancies.md) — known inconsistencies and dead code found
  while documenting the app. Check it before "fixing" something that looks wrong — it may already
  be tracked, and add to it if you find something new that's out of scope to fix.

Quick stack recap: Laravel 12 + Livewire (class-based) + Blade + Tailwind/daisyUI, PostgreSQL,
`spatie/laravel-permission` for authorization, VATSIM Connect OAuth for login, Pest for tests.

## Running things

- **Tests**: `php artisan test` (or `./vendor/bin/pest`). `phpunit.xml` currently points
  `DB_CONNECTION` at in-memory SQLite, so the suite runs standalone with no external database —
  don't assume you need Postgres up to run tests. Confirm against `phpunit.xml` if this ever looks
  wrong; it has drifted before (see `docs/discrepancies.md`).
- **App / manual verification**: use the `run-zjx-laravel` skill if available in your environment
  (Postgres container, migrate + seed, `npm run build`, `artisan serve`, a headless-browser driver).
  Otherwise see `CONTRIBUTING.md` for the equivalent manual steps.
- **Style**: `./vendor/bin/pint` before finishing any change (default Laravel preset, no
  `pint.json`). CI and reviewers expect a clean Pint run.

## Definition of done

A feature or fix is not complete until all of the following are true, not just "the code works":

1. **Documentation is updated** — `README.md`, `CONTRIBUTING.md`, and/or the relevant file under
   `docs/` (especially `docs/systems/*.md` for subsystem behavior changes) reflect the new
   behavior, setup, or usage. A change that isn't documented isn't done.
2. **Database migrations are added, not edited** — schema changes get a new migration under
   `database/migrations/`; existing migrations are never modified. Migrations must run cleanly on
   a fresh database, with matching model/factory/seeder updates in the same change.
3. **Tests exist and pass** — new routes, Livewire components, permissions, and jobs get Pest
   coverage (see `CONTRIBUTING.md`'s testing conventions: `RefreshDatabase` in Feature tests,
   `PermissionSeeder` + `assignRole` for authorization tests). `php artisan test` passes before
   you call anything finished.
4. **It matches repo and Laravel conventions** — see below and `CONTRIBUTING.md`'s Code Style
   section. Prefer the existing pattern in a neighboring file over inventing a new one.

## Conventions and gotchas (learned the hard way)

These are documented because each one has caused a real bug in this codebase — don't reintroduce
them.

- **Import controller classes in `routes/web.php`.** There's no autoloading magic for unqualified
  class names in route closures/arrays — a missing `use App\Http\Controllers\Foo;` compiles fine
  and only 500s the first time the route is hit (`ReflectionException: Class "Foo" does not
  exist`). Always add the `use` statement when wiring up a new controller.

- **`auth()->user()` can be `null` even when `Auth::check()` / `@auth` report `true`.** This
  happens with certain custom/guard edge cases (see `tests/Feature/ProfileCertificationTest.php`
  for a reproduction via a mocked guard). Never call `Auth::user()->method()` or
  `auth()->user()->method()` directly in a Blade view that's reachable without `auth` middleware
  (i.e. anything under a public/guest-accessible layout, or a component rendered sitewide like
  the navbar) — use the null-safe operator: `auth()->user()?->method()`. Same applies to Spatie's
  `@hasrole`/`@role`/`@haspermission` Blade directives, which internally do
  `auth()->user()->hasRole(...)` with no null guard: prefer
  `@if(auth()->user()?->hasRole('staff'))...@endif` over `@hasrole('staff')...@endhasrole` in
  any component that isn't strictly behind `auth` middleware.

- **Give Livewire conditional branches a `wire:key` on the elements that differ.** When a
  Blade/Livewire template swaps content via `@if($editing) ... @else ... @endif` (e.g. an
  edit-mode row vs. a view-mode row), Livewire's DOM morph can reuse the same physical DOM node
  across the two branches if they aren't keyed. Directives that bind a listener at
  element-init time — like `wire:confirm` — then persist on the reused node even after the
  Blade content changes, so a "Cancel" button that morphed from a "Delete" button can still pop
  the delete confirmation. Put a unique `wire:key` on any button/element whose attributes
  (especially `wire:click`, `wire:confirm`) differ between branches, e.g.
  `wire:key="cancel-{{ $row->id }}"` / `wire:key="delete-{{ $row->id }}"`.

- **Keep data-fetching and business logic out of anonymous Blade components.** A `@php ... @endphp`
  block at the top of a `resources/views/components/*.blade.php` file that queries models or
  calls services is a sign the component should be class-based instead. Add a class under
  `app/View/Components/` (matching the existing pattern in `Card.php`, `NavLinks.php`, etc.) with
  the logic in the constructor exposed as public properties, and keep the Blade file a pure
  template. This isn't just style — it also keeps the component testable and keeps caching
  strategy (e.g. `Cache::rememberForever` in the backing model) in one obvious place instead of
  buried in a view.

- **Never edit an existing migration.** Schema changes are always a new migration file, even for
  a change to something added earlier in the same PR/branch.

## Scope discipline

Per `CONTRIBUTING.md`: keep diffs focused. Fixing an unrelated pre-existing issue you notice along
the way is fine to call out, but do it as a separate, clearly-labeled change (or a
`docs/discrepancies.md` note) rather than folding it into an unrelated PR.
