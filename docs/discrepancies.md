# Discrepancies & Known Issues

This is a follow-up work queue produced during the repository documentation run. **Items resolved on the `fixes/discrepancies` branch have been removed from this list.** The original numbering is preserved, so gaps — no §3 or §4, and missing entries like 5.1–5.5, 5.16, 6.2, 6.5–6.9, 7.1 — mark items that have been fixed or dismissed. What remains below is still open.

Each entry lists **where** it is, **why it matters**, and a **suggested fix**. Severity is a rough triage aid, not a formal assessment. Verify each against current code before acting — line-level details may have shifted.

Ordering: [Security](#1-security) · [Project identity & licensing](#2-project-identity--licensing) · [Confirmed code bugs](#5-confirmed-code-bugs) · [Schema, model & migration mismatches](#6-schema-model--migration-mismatches) · [Dead / leftover code](#7-dead--leftover-code) · [Config & environment gaps](#8-config--environment-gaps) · [Minor / cosmetic](#9-minor--cosmetic) · [Docs corrected in this run](#10-documentation-corrected-in-this-run)

---

## 1. Security

### 1.1 — `.env.staging` holds real-looking secrets but is NOT tracked (no repo exposure)
- **Where:** `.env.staging` exists on disk locally and contains what look like real secrets — a database password, VATUSA API key, VATSIM client secret, a mail (ZeptoMail) SMTP password, and two Discord webhook URLs.
- **Status:** The file is **not** tracked by git and never appears in history (`git log --all -- .env.staging` is empty). It is already covered by the `.env.*` rule in `.gitignore`, so it cannot be committed accidentally. There is therefore **no repository exposure** of these credentials.
- **Suggested action:** No git remediation is required — keep the `.gitignore` rule in place. Rotation is not forced by any repo leak; rotate only if these values were exposed through some other channel. Continue to keep all `.env*` files except `.env.example` out of version control, and store staging/production config on the deployment host or a secret manager.

### 1.2 — Hardcoded personal data in the dev-only `/test-email` route
- **Where:** `routes/web.php` (dev/local-only route group).
- **Why it matters:** `/test-email` hardcodes a personal email address plus fixed record IDs (`User::find(...)`, `TrainingAssignment::find(1)`). Not a secret, but brittle and it leaks a personal address into the repo.
- **Suggested fix:** Parameterize via query string or config, or remove the route.

---

## 2. Project identity & licensing

### 2.2 — `composer.json` still carries starter-kit identity
- **Where:** `composer.json` — `"name": "laravel/blank-livewire-starter-kit"` and the generic starter description.
- **Suggested fix:** Rename to the project (e.g. `zjx-artcc/site-laravel`) and write a real description.

---

## 5. Confirmed code bugs

These are functional defects found while documenting. Grouped by system. (Training and Auth items 5.1–5.5 and 5.16 were fixed on the `fixes/discrepancies` branch.)

### Events
> Events is a work in progress — these are left for that effort, not fixed here.

- **5.6 `EventPositionAssignmentController::store()` is unrouted and buggy.** No route references it (`routes/web.php`), and it validates `position_id` but then reads `$data['position_name']` (never validated), so it would throw even if wired. Consequently there is **no working "staff assigns a position" path** — `assigned_position`/`position_status` are never written beyond the DB default. **Fix:** decide whether staff assignment is a feature; if so, implement and route it correctly; if not, delete the controller.
- **5.7 `EventPositionPresetController` redirects to a nonexistent route name.** `store()`/`update()`/`destroy()` redirect to `position-presets.index`, but the resource is named `admin.events.position-presets`, so the real name is `admin.events.position-presets.index`. These redirects throw `RouteNotFoundException`. **Fix:** use the fully-qualified route name.
- **5.8 `EventFieldController` only implements `index()` but is a full resource.** `event-fields` is registered as `Route::resource`, so create/store/edit/update/destroy hit missing methods. **Fix:** restrict the resource to `->only(['index'])` or implement the actions.
- **5.9 `EventRegistration::store()` has duplicate array keys.** The `EventPosition::create([...])` call lists `requested_position`, `start`, `end`, `notes` twice; PHP keeps the last occurrence, so the *validated* values are silently discarded in favor of the raw component properties. **Fix:** remove the duplicate keys and keep the validated values.

### Visiting controllers
- **5.10 `visit.update` route has no controller method.** `PUT /admin/visit-requests/{visitRequest}` → `VisitFacilityController@update`, but there is no `update()` method → 500. **Fix:** implement or remove the route.
- **5.11 `manage.blade.php` branches on nonexistent `$request->approved`.** `VisitorRequest` has no `approved` column/cast/accessor (status is the `VisitRequestStatus` enum), so the expression is always falsy and the list never shows Approved/Denied states. **Fix:** branch on `status`.

### Certifications
> Certifications has an open PR merging soon — these are expected to be resolved there.

- **5.12 `UserCertification` references a nonexistent FK column.** `app/Models/UserCertification.php` `$fillable` and `certificationLevel()` use `facility_certification_level_id`, but the migration column (and composite-PK member) is `certification_level_id`. The relationship cannot resolve. **Fix:** rename the model references to `certification_level_id`.
- **5.13 Certification display on the roster is broken.** `resources/views/roster/index.blade.php` uses `$user->certifications->where('facility_id', …)` (no `facility_id` on `UserCertification`) and `$cert->level->identifier` (the relation is `certificationLevel`, not `level`, and `CertificationLevel` has `abbreviation`, not `identifier`). **Fix:** correct the relation/attribute names once 5.12 is resolved.
- **5.14 `certification-level-row` Livewire component does not exist.** `resources/views/livewire/certification-levels-table.blade.php` renders `@livewire('certification-level-row', …)`, but no such class/view exists → render failure. **Fix:** create the component or change the table to not reference it.
- **5.15 No write path for `user_certifications`.** No controller, route, Livewire component, or seeder ever creates/deletes rows in `user_certifications`. The data model exists but user certifications cannot be granted or revoked through the app. **Fix:** build the grant/revoke UI, or document it as an intentionally external/DB-only process.

---

## 6. Schema, model & migration mismatches

(6.2 and 6.5–6.9 were fixed on the `fixes/discrepancies` branch; 6.3 was confirmed correct — `training_type` is a VATUSA typeset, not a rating.)

### 6.1 — `FeaturedField::events()` targets the wrong pivot table
> Events WIP — left for that effort.
- **Where:** `app/Models/FeaturedField.php` uses `event_featured_fields` (plural); the migration creates `event_featured_field` (singular).
- **Why it matters:** The relationship breaks if invoked. Separately, `Event` defines *no* relationship to `FeaturedField` — events store featured fields in the `events.featured_fields` **JSON column** instead, so the `featured_fields` table and `event_featured_field` pivot are effectively orphaned (the table is only read to build a picklist). Featured fields are dual-modeled (table + JSON).
- **Suggested fix:** Decide on one model (JSON vs relational). If keeping the pivot, fix the table name and add the `Event` side; if not, drop the unused table/pivot.

### 6.4 — `User::events()` pivot column name mismatch
> Events WIP — left for that effort.
- **Where:** `app/Models/User.php` `events()` uses `->withPivot('…','note',…)`; the `event_positions` column is `notes` (plural). Also `EventPosition::events()` declares `belongsToMany(Event)`, which is nonsensical for a row that belongs to one event via `event_id` and appears unused.
- **Suggested fix:** Use `notes`; remove the unused `belongsToMany`.

---

## 7. Dead / leftover code

(7.1, the unrouted `ProfileController`, was removed on the `fixes/discrepancies` branch.)

- **7.2 `CreateEvent` Livewire component is a stub and unused.** `app/Livewire/CreateEvent.php` has an empty `save()`, hardcoded `featuredFieldsOptions` (`['KMCO','KJAX','KDAB']`), and no Blade references it. Real event creation goes through `EventController::store()`. (Events WIP.)
- **7.3 `VatusaRole` DTO is empty.** `app/DTOs/VatusaRole.php` contains only a namespace declaration; facility roles are parsed inline in `VatusaFacilityInfoDTO`.
- **7.4 `Welcome` mailable has no production trigger.** `app/Mail/Welcome.php` is only instantiated by the dev-only `/test-email` route; nothing sends it on first login.
- **7.5 `SyncTrainingTickets` is never scheduled.** `routes/console.php` schedules only `SyncRoster` and `UpdateOnlineControllers`. `SyncTrainingTickets` runs only via the dev-only `/sync-training` route, so in production nothing pushes training tickets to VATUSA automatically. Confirm whether that is intended.
- **7.6 `zeptomail` mailer transport has no driver.** `config/mail.php` defines a `zeptomail` mailer with `'transport' => 'zeptomail'`, but no package provides it and nothing calls `Mail::extend('zeptomail', …)`. Setting `MAIL_MAILER=zeptomail` would fail. ZeptoMail is actually reached via the standard `smtp` mailer. Remove the dead entry or wire a real transport.
- **7.7 Inertia SSR leftovers.** `composer.json` has `dev:ssr` (`npm run build:ssr`, `php artisan inertia:start-ssr`), but Inertia is not a dependency and `package.json` has no `build:ssr`. Starter-kit residue.
- **7.8 Volt is effectively unused.** `livewire/volt` is installed and `app/Providers/VoltServiceProvider.php` mounts `resources/views/livewire` and `resources/views/pages` (the latter does not exist), but every component is class-based under `app/Livewire/`. Consider removing Volt if unused.
- **7.9 Commented-out Socialite registration.** `app/Providers/AppServiceProvider.php` has an older `Socialite::extend('vatsim', …)` block commented above the active one. Delete it.
- **7.10 Test placeholders / starter stubs.** `tests/Pest.php` still defines a stub `function something()`; `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php` are starter placeholders. Remove.
- **7.11 Unused event view.** `resources/views/events/manage.blade.php` appears unused — `EventController::manage()` returns `manage-events.index`. (Events WIP.)

---

## 8. Config & environment gaps

- **8.1 Env vars read by config but missing from `.env.example`.** `VATSIM_API_URL` (read in `config/app.php`, used by `UpdateOnlineControllers`; only `VATSIM_AUTH_URL` is in the example), `LOG_DISCORD_WEBHOOK_URL` / `LOG_DISCORD_IGNORE_EXCEPTIONS` (read in `config/logging.php`), and `LARAVEL_OPTIMIZE` (consumed by `entrypoint.sh`). A fresh clone lacks these. **Fix:** add them to `.env.example` with safe placeholders.
- **8.2 Scout driver is implicit.** No `config/scout.php` is published and no `SCOUT_DRIVER` appears in any `.env`. The `collection` driver is only the vendor default (`env('SCOUT_DRIVER', 'collection')`). **Fix:** publish `config/scout.php` and/or set `SCOUT_DRIVER` explicitly so the choice is intentional.
- **8.3 Environment-name mismatch for dev conveniences.** `SyncRoster::handle()` grants roles to the "Web" test users only when `App::environment() == 'development'`, while the dev-only routes use `App::environment('development', 'local')`. On a `local` environment the sync convenience won't fire even though `/sync` exists. **Fix:** standardize the environment name(s).

---

## 9. Minor / cosmetic

- **9.1** `roster/index.blade.php` reads `env('VATUSA_FACILITY')` directly in the view — breaks under `php artisan config:cache`. Use `config()`.
- **9.2** Staff views (`staff-card`, training-team/assistant blocks) dereference `$staff->user` without a null guard, even though out-of-division staff can have no local user. `RosterController` carries a `// fix:` comment about this. Latent null-deref.
- **9.3** Visiting checklist `needsBasic` may be semantically inverted in `visit/create.blade.php` (VATUSA `needbasic` mapped to a positive-sounding label). Verify the intended true/false meaning.
- **9.4** `VisitFacilityController::approve()` stores the raw `operatingInitials` but only uppercases the value for its duplicate-check; a lowercase submission could be stored un-normalized. Normalize before storing.
- **9.5** `User::updateFromVatusa()` returns the `upsert()` affected-row count, not a `User` model, despite call sites reading as if a model comes back. Verify callers.
- **9.6** `RosterController` has a typo'd local variable `$certificaionFacilities` (passed correctly to the view; cosmetic).
- **9.7** `Staff::fromFacilityInfoDTO` sets `title_long` to "Training Administrator" for both `TA` and `ATA` (ATA should likely be "Assistant Training Administrator").
- **9.8** `routes/web.php` has a `TODO: make store` on the `training-assignment/create` POST route (a `create` action used for a store operation), and `training-assignments.destroy` takes the id via request payload rather than a route parameter — inconsistent with the other resourceful routes.
- **9.9** `UserController::update()` can't clear a user's operating initials from the edit form: it assigns `strtoupper($validated['operatingInitials'] ?? $user->operating_initials)`, so an empty submission (null after `ConvertEmptyStringsToNull`) keeps the old value silently. The only way to unassign an OI is the [OI Matrix](systems/operating-initials.md). Fix by distinguishing "field absent" from "field submitted empty" (e.g. `$request->has('operatingInitials')`) and storing `null` when empty — the `User` mutator already converts `''`/`null` to `null`.

---

## 10. Documentation corrected in this run

The previous `docs/certification-system-overview.md` contained an inaccurate ER diagram: singular table names (`certification_level`, `certification_facility`) where the real tables are plural (`certification_levels`, `certification_facilities`), a one-to-one relationship where the schema is many-to-many via `user_certifications` (composite PK `user_id` + `certification_level_id`), and missing `timestamps`. That file was **removed** and replaced by a corrected diagram in [`systems/certifications.md`](systems/certifications.md).
