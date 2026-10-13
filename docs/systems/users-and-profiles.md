# Users and Profiles

## Purpose

This document describes how the ZJX ARTCC site represents controllers as `User`
records, how it renders public profile pages (with training sub-tabs), how
staff manage the roster from the admin area, and how roster search is powered by
Laravel Scout's DB-backed `collection` driver. It is written for developers and
contributors working on user-facing profile pages or admin roster tooling.

VATUSA/VATSIM synchronization is only summarized here. For the details of how
records are pulled from and reconciled with VATUSA, see
[../vatsim-integration.md](../vatsim-integration.md).

## Key concepts

- **A `User` is a controller.** Every account corresponds to a VATSIM member.
  The `users.id` column stores the member's VATSIM **CID**; the app writes the
  CID into `id` explicitly rather than relying on auto-increment (see Gotchas).
- **Rating** is stored as an integer and cast to the `App\Enums\ControllerRating`
  enum (values run from `INA = -1` through `ADM = 12`). Use
  `$user->rating->mapToString()` to render it.
- **Operating initials** (`operating_initials`) are a unique, optional two-character
  code. The model normalizes them to uppercase on both read and write.
- **Default role.** Newly created users are automatically assigned the `core`
  Spatie role in the model's `booted()` hook.
- **Rostered flag.** `rostered` (boolean) marks members who are on the ZJX roster.
  The profile training sub-tabs are gated on it.
- **Search** is provided by Laravel Scout using the `collection` driver, which
  queries the local database — there is no external search service (Algolia,
  Meilisearch, etc.) to run.

## Data model

### The `User` model (`app/Models/User.php`)

Mass-assignable attributes (`$fillable`): `id`, `first_name`, `last_name`,
`email`, `rating`, `joined_at`, `division`, `facility`, `rostered`,
`discord_id`, `operating_initials`.

Casts: `email_verified_at` → datetime, `password` → hashed, `rating` →
`ControllerRating`, `joined_at` → datetime, `rostered` → boolean.

Accessors / mutators (all via `Attribute`):

- `operatingInitials` — upper-cases on get and set.
- `firstName` / `lastName` — `ucfirst` on get and set.
- `name` — read-only, returns `"First Last"`.
- `nameReversed` — read-only, returns `"Last, First"`.
- `initials()` — a plain method returning the first letters of the display name.

VATUSA helpers (details in [../vatsim-integration.md](../vatsim-integration.md)):

- `createFromVatusa(int $id)` — fetches a single user from the VATUSA API and
  passes the payload to `updateFromVatusa`.
- `updateFromVatusa(VatusaRosterUser $vatusaUser)` — upserts the user keyed on
  `id` (the CID).

Traits used: `HasFactory`, `Notifiable`, `HasRoles` (Spatie permissions),
`LogsActivity` (Spatie activity log — see
[audit-logging.md](audit-logging.md)), and `Searchable` (Scout).

### Relationships

| Relationship | Type | Related model | Notes |
| --- | --- | --- | --- |
| `staffRoles` | hasMany | `Staff` (`user_id`) | Staff positions held |
| `trainingAssignmentsAsStudent` | hasMany | `TrainingAssignment` (`user_id`) | Ordered `created_at desc` |
| `trainingAssignmentsAsInstructor` | hasMany | `TrainingAssignment` (`instructor_id`) | |
| `trainingTicketsAsStudent` | hasMany | `TrainingTicket` (`user_id`) | Ordered `created_at desc` |
| `trainingTicketsAsInstructor` | hasMany | `TrainingAssignment` (`instructor_id`) | See Gotchas |
| `soloCerts` | hasMany | `SoloCert` (`user_id`) | Ordered `created_at desc` |
| `visitRequests` | hasMany | `VisitorRequest` (`user_id`) | Ordered `created_at desc` |
| `certifications` | hasMany | `UserCertification` (`user_id`) | |
| `events` | belongsToMany | `Event` (pivot `event_positions`) | Pivot: `requested_position`, `start`, `end`, `note`, `position_status`, timestamps |

### Relevant columns

The `users` table is created by `database/migrations/0001_01_01_000000_create_users_table.php`
(`id`, `first_name`, `last_name`, `email` unique, timestamps) and extended by
`database/migrations/2025_10_30_150500_add_user_columns.php`, which adds:
`rostered` (default false), `rating` (integer, default 1), `division` (3),
`facility` (3), `joined_at`, `discord_id`, `profile_image_route`
(default `images/default_profile.jpg`), `biography`, and
`operating_initials` (2, nullable, **unique**).

## Flows

### Viewing a public profile

`GET users/{user}` → `UserController@show` loads the user via `findOrFail` and
renders `resources/views/users/show.blade.php`. Profile pages use the
`resources/views/layouts/profile.blade.php` layout, which draws a tab strip:
**General Info** (`users.show`) is always shown; **Training Tickets**,
**Training Assignments**, and **Solo Certs** appear only to users with the
`training` role (`@role('training')`).

The three training sub-tabs are separate controller actions, each of which
first rejects non-rostered users (redirect back with an error) and then
paginates:

- `GET users/{user}/training-tickets` → `trainingTickets` → `users.training-tickets`
  (paginated 25, page name `ticketsPage`).
- `GET users/{user}/training-assignments` → `trainingAssignments` →
  `users.training-assignments` (page name `assignmentsPage`).
- `GET users/{user}/solo-certs` → `soloCerts` → `users.solo-certs`
  (page name `soloCertsPage`).

### Editing a profile

`GET users/{user}/edit` → `UserController@edit` renders
`resources/views/users/edit.blade.php`. Access is allowed only to the profile's
owner or a user with the `manage users` permission; otherwise a `403` is
returned.

`PUT/PATCH users/{user}` → `UserController@update` validates `operatingInitials`
(nullable string, exactly 2 chars), `image` (optional image ≤ 2 MB), and
`biography` (optional, ≤ 1000 chars). Ownership/`manage users` is re-checked.
An uploaded image is stored on the `public` disk as `profile/profile_{id}.{ext}`
and recorded as that disk-relative path in `profile_image_route`. Its public URL is
generated with `Storage::disk('public')->url(...)`; older `storage/profile/...`
records remain supported. The upload accepts JPEG, PNG, and GIF (not SVG, because
these files are served publicly). Operating initials can only be changed by
a user with `manage users`, and the controller rejects the change (redirect back
with an `error`) if the initials are already assigned to another user.

### Searching the roster

The roster page renders the `UserTable` Livewire component
(`@livewire('user-table')` in `resources/views/user-management/index.blade.php`).
`UserTable` (`app/Livewire/UserTable.php`) extends `SortableTable`
(`app/Livewire/SortableTable.php`) and, on each render, runs
`User::search($this->search)->orderBy($sortField, $sortDirection)->paginate(25)`.
Because Scout uses the `collection` driver, this search executes against the
database. The default sort is `last_name asc`.

`User::toSearchableArray()` exposes `name`, `email`, `id`,
`rating` (via `mapToString()`), and `facility` to the search index.

`SortableTable` is a small base component declaring the shared `search`,
`sortField`, and `sortDirection` public properties; `UserTable` supplies the
concrete defaults and the `render()` method.

## Permissions / middleware

- **Public profile pages** (`users.show` and its sub-tabs) require no auth.
  The training sub-tabs are additionally hidden in the layout unless the viewer
  has the `training` role, and the actions themselves reject non-rostered target
  users.
- **Edit / update** require the authenticated user to be the profile owner **or**
  hold the `manage users` permission. Changing operating initials additionally
  requires `manage users`.
- **Admin roster management** (`GET admin/users`, route name
  `manage-users.index`, `UserManagementController@index`) lives inside the
  `admin` route group, which is guarded by `permission:view dashboard`. The
  roster table itself reveals email / joined / last-activity columns and the
  Edit action only to viewers with `manage users` (via `@haspermission` in the
  Blade).

## Key files

| Path | Role |
| --- | --- |
| `app/Models/User.php` | The `User` model: casts, attributes, relationships, VATUSA helpers, `toSearchableArray` |
| `app/Enums/ControllerRating.php` | Rating enum + `mapToString()` |
| `app/Http/Controllers/UserController.php` | Public profile show/edit/update + training sub-tabs |
| `app/Http/Controllers/UserManagementController.php` | Admin roster listing |
| `app/Livewire/UserTable.php` | Livewire roster table (search + sort + paginate) |
| `app/Livewire/SortableTable.php` | Base Livewire component for sortable tables |
| `routes/web.php` | `users` resource + `users/{user}/*` sub-tab routes, `admin/users` |
| `resources/views/users/` | `show`, `edit`, and training sub-tab views |
| `resources/views/layouts/profile.blade.php` | Profile layout with the tab strip |
| `resources/views/user-management/index.blade.php` | Admin roster page (hosts `user-table`) |
| `resources/views/livewire/user-table.blade.php` | Roster table markup |
| `resources/views/components/search.blade.php` | Generic search form component (`<x-search>`) |
| `resources/views/components/search-training-assignments.blade.php` | Search form with training-type filter |
| `database/migrations/2025_10_30_150500_add_user_columns.php` | Adds roster/rating/OI columns |

### Searchable models

The following models use the Scout `Searchable` trait; all are searched through
the DB-backed `collection` driver:

| Model | Searchable fields (`toSearchableArray`) |
| --- | --- |
| `User` | `name`, `email`, `id`, `rating`, `facility` |
| `TrainingAssignment` | `name`, `trainingType`, `status`, `date` |
| `TrainingTicket` | `user_id`, `instructor_id`, `student_name`, `instructor_name`, `position`, `date` |
| `SoloCert` | `user`, `position`, `issued_by_id` |
| `VisitorRequest` | `name`, `reason` |

## Gotchas

- **The CID is stored in the auto-increment `id` column.** The migration uses
  `$table->id()` (a standard auto-incrementing bigint), and the model does not
  set `$incrementing = false` or `$keyType`. Records are created with the CID as
  the `id` value because `id` is in `$fillable` and `updateFromVatusa` upserts on
  `id`. Do not rely on auto-increment for user creation — always supply the CID.
- **Scout driver comes from the package default.** There is no published
  `config/scout.php` and no `SCOUT_DRIVER` in the environment files, so the
  effective driver is the vendor default, `collection`. Adding a `SCOUT_DRIVER`
  env value would silently switch the search backend.
- **`trainingTicketsAsInstructor` returns `TrainingAssignment`, not
  `TrainingTicket`.** The relationship on the `User` model points at
  `TrainingAssignment::class` with the `instructor_id` key, making it a duplicate
  of `trainingAssignmentsAsInstructor`. Verify intent before using it.
- **`updateFromVatusa` returns the upsert result, not the model.**
  `User::upsert()` returns an affected-row count; callers that expect a `User`
  instance back will not get one.
