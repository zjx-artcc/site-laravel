# Audit Logging

## Purpose

This document describes how the ZJX ARTCC site records an audit trail of model
changes using `spatie/laravel-activitylog`, and how staff view and export that
trail from the admin area. It is written for developers and contributors who
need to add auditing to a model or work on the audit-log viewer.

## Key concepts

- **Activity log entries** are rows in the `activity_log` table, each capturing a
  *subject* (the model that changed), a *causer* (the user who made the change,
  if any), an `event` (`created` / `updated` / `deleted`), and a `properties`
  JSON blob holding the changed attributes (`attributes` and, for updates, `old`).
- **Opt-in per model.** A model is audited by using the
  `Spatie\Activitylog\Traits\LogsActivity` trait and defining
  `getActivitylogOptions()`, which declares exactly which attributes to log via
  `LogOptions::defaults()->logOnly([...])`.
- **The viewer is admin-only** and lets staff filter by controller (CID) and by
  record type, then either browse the paginated log or stream a CSV export.

## Data model

### Audited models

| Model | File | Logged attributes (`logOnly`) |
| --- | --- | --- |
| `User` | `app/Models/User.php` | `rating`, `email`, `first_name`, `last_name`, `id`, `operating_initials` |
| `TrainingAssignment` | `app/Models/TrainingAssignment.php` | `user_id`, `instructor_id`, `status` |
| `TrainingTicket` | `app/Models/TrainingTicket.php` | `user_id`, `instructor_id`, `session_date`, `duration`, `movements`, `score`, `notes`, `location`, `ots_status` |
| `StatisticsPrefixes` | `app/Models/StatisticsPrefixes.php` | `name` |
| `Loa` | `app/Models/Loa.php` | `user_id`, `start_date`, `end_date`, `status`, `response` |
| `Faq` | `app/Models/Faq.php` | `category`, `question`, `answer`, `sort_order`, `is_published` |
| `FaqSetting` | `app/Models/FaqSetting.php` | `key`, `value` |
| `Publication` | `app/Models/Publication.php` | `name`, `description`, `publication_category_id`, `original_filename` |
| `PublicationCategory` | `app/Models/PublicationCategory.php` | `title`, `description`, `display_order`, `show_in_nav` |

All of these use `LogOptions::defaults()`, so only the listed attributes are logged
and (by Spatie's defaults) empty diffs are not recorded. `Publication` and
`PublicationCategory` also chain `logOnlyDirty()`, so their update entries hold only the
attributes that actually changed. `Loa` deliberately leaves out the free-text `reason`.

### The `activity_log` table

The table is created and extended by three migrations:

- `database/migrations/2025_11_11_035813_create_activity_log_table.php`
- `database/migrations/2025_11_11_035814_add_event_column_to_activity_log_table.php`
- `database/migrations/2025_11_11_035815_add_batch_uuid_column_to_activity_log_table.php`

Key columns used by the viewer/export: `event`, `description`, `subject_type`,
`subject_id`, `causer_type`, `causer_id`, `properties` (JSON), and `created_at`.
For the full schema see [../database.md](../database.md).

## Flows

### Viewing the log

`GET admin/logs` → `AuditLogController@index` (route name `logs.index`) builds:

- `$controllers` — the full roster (`id`, `first_name`, `last_name`, `rating`),
  ordered by last then first name, for the controller typeahead.
- `$selectedController` — the `User` for the `cid` query param, if present.
- `$recordTypes` — the distinct `subject_type` values in the log, mapped to
  human-readable labels via `Str::headline(class_basename(...))`, for the type
  filter.
- `$logs` — the filtered query, eager-loading `causer` only, paginated 25 with
  the query string preserved.

The subject relation is deliberately **not** eager-loaded, because a stored
`subject_type` may reference a model class that no longer exists in the current
codebase; instantiating every type up front would fail.

The base query (`filteredQuery`, shared by the viewer and the export) applies:

- `cid` filter — matches entries where the user is *either* the causer
  (`causer_type = User`, `causer_id = cid`) *or* the subject
  (`subject_type = User`, `subject_id = cid`).
- `type` filter — matches `subject_type = type`.
- Ordered `created_at desc`.

The view is `resources/views/audit-log/index.blade.php` (extends
`layouts.admin`), which renders the controller picker (Alpine typeahead) and the
diff of each entry's `properties`.

### Exporting to CSV

`GET admin/logs/export` → `AuditLogController@export` (route name `logs.export`)
streams a CSV download named `audit-log-{Ymd-His}Z.csv`. It reuses
`filteredQuery($cid, $type)` so the export matches whatever the viewer is
showing, and accepts an optional `limit` query param:

- `limit > 0` — takes the most recent N rows (`limit()->get()`).
- otherwise — streams all matching rows in chunks of 500.

Columns: Time (UTC/Zulu), Action, Who, Causer ID, Record Type, Record ID,
Record Name, What Changed. `What Changed` is produced by `describeChanges()`,
which flattens the `properties` diff into `Field: from -> to` (for updates) or
`Field: value` (otherwise). `subjectName()` resolves the subject's display name
using Laravel's `rescue()` so a missing/renamed subject class does not break the
export; causers with no record are shown as `System`.

## Permissions / middleware

Both audit-log routes live in the `admin` route group (guarded by
`permission:view dashboard`) and are additionally wrapped in a
`permission:view audit logs` middleware group in `routes/web.php`:

```php
Route::middleware('permission:view audit logs')->group(function() {
    Route::get('logs', [AuditLogController::class, 'index'])->name('logs.index');
    Route::get('logs/export', [AuditLogController::class, 'export'])->name('logs.export');
});
```

So a viewer must have both `view dashboard` and `view audit logs`.

## Key files

| Path | Role |
| --- | --- |
| `app/Http/Controllers/AuditLogController.php` | Viewer (`index`) + CSV export (`export`) + shared query/formatting helpers |
| `resources/views/audit-log/index.blade.php` | Audit-log viewer page |
| `routes/web.php` | `admin/logs` and `admin/logs/export` routes + permission gate |
| `app/Models/User.php` | `getActivitylogOptions()` for user changes |
| `app/Models/TrainingAssignment.php` | `getActivitylogOptions()` for training assignments |
| `app/Models/TrainingTicket.php` | `getActivitylogOptions()` for training tickets |
| `app/Models/StatisticsPrefixes.php` | `getActivitylogOptions()` for statistics prefixes |
| `app/Models/Loa.php` | `getActivitylogOptions()` for LOAs |
| `app/Models/Faq.php`, `app/Models/FaqSetting.php` | `getActivitylogOptions()` for FAQs and FAQ settings |
| `app/Models/Publication.php`, `app/Models/PublicationCategory.php` | `getActivitylogOptions()` for publications and categories |
| `database/migrations/2025_11_11_035813_create_activity_log_table.php` | Base `activity_log` table |
| `database/migrations/2025_11_11_035814_add_event_column_to_activity_log_table.php` | Adds `event` column |
| `database/migrations/2025_11_11_035815_add_batch_uuid_column_to_activity_log_table.php` | Adds `batch_uuid` column |

## Gotchas

- **Only the `logOnly()` attributes are recorded.** Changing an attribute that
  is not in a model's `getActivitylogOptions()` list produces no log entry, and
  for updates Spatie's defaults skip entries with an empty diff. If you add a
  column that should be audited, add it to the model's `logOnly()` list.
- **Stored subject/causer types can outlive their classes.** The viewer avoids
  eager-loading subjects, and both the viewer and export tolerate model classes
  that no longer exist (`rescue()` around `$log->subject`). Do not add code that
  instantiates every `subject_type` up front.
- **The `cid` filter is user-centric.** It only matches when the selected user
  is the causer or the subject *as a `User`*. It will not surface, say, a
  `TrainingTicket` about that user unless the ticket itself was logged with the
  user as subject/causer.
- **Timestamps are emitted in UTC/Zulu** in the export (`created_at->utc()`),
  regardless of the app timezone.
