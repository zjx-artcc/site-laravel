# Training system

The training system covers three related record types that a facility's training
department uses to move a student through their controller certifications:

- **Training tickets** — a written record of a single training session, pushed to
  VATUSA as an official training record.
- **Training assignments** — the link between a student and an instructor for an
  ongoing course (S1, S2, ..., or an Orlando/MCO endorsement).
- **Solo certifications** — a temporary authorization letting a student control a
  position solo before they are fully certified, mirrored to VATUSA.

All three live under `app/Http/Controllers/Training/`, share the `role:training`
admin routes, and hand off to VATUSA through queued jobs. For the detail of the
VATUSA HTTP API those jobs call, see [docs/vatsim-integration.md](../vatsim-integration.md).

---

## Purpose

Give the training department a place to:

- Log each training session and have it appear on the student's VATUSA training
  record automatically.
- Track who is training whom, let instructors claim/drop students, and let the
  training staff move an assignment through its lifecycle (active → mock OTS →
  checkout → complete, etc.).
- Issue and revoke 30-day solo certifications, keeping VATUSA in sync both ways.

---

## Key concepts

- **Student vs. instructor.** Every record links two users: the `user_id`
  (student) and an `instructor_id`. On an assignment the `instructor_id` is
  nullable — an unclaimed request has no instructor yet.
- **Position string.** Tickets and solo certs both store a `position` validated
  against the regex `^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR)$`
  (e.g. `JAX_CTR`, `MCO_TWR`, `ORL_GND`).
- **Training type** (`app/Enums/TrainingType.php`) — the course a student is
  enrolled in on an assignment.
- **Training status** (`app/Enums/TrainingStatus.php`) — where an assignment sits
  in its lifecycle.
- **VATUSA sync.** Tickets and solo certs are the two record types mirrored to
  VATUSA. Assignments are internal to the site (they only fire a Discord webhook
  and email).
- **Roles.** The training department uses two Spatie roles: `training`
  (day-to-day training staff) and `instructor` (senior staff with revoke /
  manage powers). See [Permissions & middleware](#permissions--middleware).

### Enum: `TrainingType` (int-backed)

| Case | Value | `mapToString()` |
|------|-------|-----------------|
| `S1` | 1 | `S1` |
| `S2` | 2 | `S2` |
| `S3` | 3 | `S3` |
| `C1` | 4 | `C1` |
| `MCO_GND` | 5 | `MCO GND` |
| `MCO_TWR` | 6 | `MCO TWR` |
| `MCO_APP` | 7 | `F11 TRACON` |

The `MCO_*` cases are the Orlando (KMCO) local endorsements. Note that
`MCO_APP` renders as `F11 TRACON` (the Central Florida TRACON), not "MCO APP".

### Enum: `TrainingStatus` (int-backed)

| Case | Value | `label()` | `badgeClass()` |
|------|-------|-----------|----------------|
| `ACTIVE` | 1 | `Active` | `badge-success` (green) |
| `SOLO` | 2 | `Solo Cert` | `badge-info` (cyan) |
| `MOCK` | 3 | `Mock OTS` | `badge-warning` (amber) |
| `CHECKOUT` | 4 | `Checkout` | `badge-secondary` (purple) |
| `COMPLETE` | 5 | `Complete` | `badge-primary` (blue) |
| `FORFEIT` | 6 | `Forfeit` | `badge-error` (red) |

`badgeClass()` returns the daisyUI classes (plus the matching `text-*-content`)
used to render a status as a filled chip, matching how certifications are shown
on a user profile. The palette reads as a progression — green while training is
live, cool tones through the intermediate stages, brand blue once complete, red
on forfeit — and green is reserved for `ACTIVE` so it never competes with
`COMPLETE`.

Render a status through the `<x-training-assignment-status-label>` component
rather than printing `label()` directly; it is used by both the staff training
assignments table (`components/training-assignment-table.blade.php`) and the
profile table (`components/training-assignments-profile-table.blade.php`).

Tailwind picks these class names up out of `app/Enums/TrainingStatus.php` via its
automatic source detection, so a new case needs no `@source` change in
`resources/css/app.css` — but it does need a real class string in the `match`,
not an interpolated one.

---

## Data model

Three tables, all foreign-keyed to `users`.

```mermaid
erDiagram
    users ||--o{ training_tickets : "is student (user_id)"
    users ||--o{ training_tickets : "is instructor (instructor_id)"
    users ||--o{ training_assignments : "is student (user_id)"
    users ||--o{ training_assignments : "is instructor (instructor_id)"
    users ||--o{ solo_certs : "holds (user_id)"
    users ||--o{ solo_certs : "issued (issued_by_id)"

    training_tickets {
        int id PK
        int user_id FK "student"
        int instructor_id FK
        timestamp session_start
        timestamp session_end
        string position
        int movements
        int score "1-5"
        longText notes
        longText instructor_notes "nullable, staff-only"
        int location "0-2"
        boolean vatusa_synced
        string vatusa_id "nullable"
        timestamps created_at_updated_at
    }

    training_assignments {
        int id PK
        int user_id FK "student"
        int instructor_id FK "nullable"
        boolean active
        int training_type "TrainingType enum"
        int status "TrainingStatus enum"
        timestamps created_at_updated_at
    }

    solo_certs {
        int id PK
        int user_id FK "student"
        int issued_by_id FK
        string position
        boolean revoked
        timestamps created_at_updated_at
    }
```

### `TrainingTicket` (`app/Models/TrainingTicket.php`)

- Relations: `student()` (→ `user_id`), `instructor()` (→ `instructor_id`).
- `duration` is a computed `Attribute` — it diffs `session_start` and
  `session_end` and returns an `HH:MM` string (falls back to `"00:00"` if the
  dates can't be parsed). There is no `duration` column; it is what the VATUSA
  sync job sends as the record duration.
- `vatusa_synced` (bool) and `vatusa_id` (string, nullable) track sync state.
  `vatusa_id` was added later by `2026_06_11_000001_add_vatusa_id_to_training_tickets_table`.
- `notes` and `instructor_notes` use `longText`, with no application-level maximum
  length. `instructor_notes` is nullable, captured with a Markdown-enabled editor,
  staff-only, and is shown
  on the ticket only to users with the `training` role and is **never** sent to
  VATUSA — `SyncTrainingTickets` only forwards the student-facing `notes` column.
  Both note fields are purified when saved and again before rich-text rendering.
- Uses Laravel Scout `Searchable` (student/instructor name + id, position, date)
  and Spatie `LogsActivity`.

### `TrainingAssignment` (`app/Models/TrainingAssignment.php`)

- Relations: `student()` (→ `user_id`), `instructor()` (→ `instructor_id`).
- Casts: `active` → bool, `status` → `TrainingStatus`, `training_type` →
  `TrainingType`.
- Has a `status()` mutator that lowercases the incoming value before it is stored.
- Searchable by student name, training type (`mapToString()`), status, date.

### `SoloCert` (`app/Models/SoloCert.php`)

- Relations: `user()` (→ `user_id`), `issuedBy()` (→ `issued_by_id`).
- Casts: `revoked` → bool, timestamps → datetime.
- **Expiry logic:** solo certs are hard-coded to a 30-day life.
  - `expires` = `created_at->addDays(30)`.
  - `expired` = `expires->isBefore(now())`.
  These are computed accessors — there is no `expires`/`expired` column, and the
  30 days is not configurable. The migration comment ("auto expires in 30 days")
  refers to this model logic, not a DB default.

---

## Flows

### Create a training ticket

`TrainingTicketController@store` (`POST /admin/training/tickets`):

1. Validates `student`, `position` (regex above), `location` (0–2),
   `sessionStart`/`sessionEnd` (`sessionEnd` must be after `sessionStart`),
   `movements`, `score` (1–5), `notes`, and optional `instructor_notes`
   (staff-only). The note fields have no application-level maximum length.
2. Rejects the ticket if the instructor picks themselves as the student.
3. Creates the `TrainingTicket` with `instructor_id = Auth::user()->id`.
4. Emails the student (bcc the instructor) via `TrainingTicketCreated`.
5. Dispatches `SyncTrainingTickets` to push to VATUSA.
6. Redirects to `training-tickets.show`.

> The controller's `edit()` / `update()` methods are marked **`!!!! NOT IN USE !!!!`**
> and block edits once `vatusa_synced` is true; `destroy()` is empty.

### Request / create a training assignment (student-initiated, public)

`TrainingAssignmentController@create` (`POST training-assignment/create`, `auth`
only — outside the `role:training` group). Despite the name it *creates* the
record (there is a `TODO: make store` note on the route):

1. Validates `trainingType` (int, required) and confirms it maps to a valid
   `TrainingType`.
2. Rejects if the caller already has an active assignment, or is not `rostered`.
3. Creates the assignment with `instructor_id = null`.
4. Dispatches `SendTrainingRequestToWebhook` (Discord) and emails the requester
   via `TrainingAssignmentCreated`.

### Claim / drop / update / deactivate an assignment (instructor / staff)

- **Claim** — `@claim` (`PUT /admin/training/assignments/claim/{assignment}`):
  requires the `claim students` permission; refuses if the instructor is the
  student, or the assignment is inactive; sets `instructor_id` to the caller and
  emails the student (`TrainingAssignmentUpdated`).
- **Drop** — `@drop` (`PUT /admin/training/assignments/drop/{assignment}`):
  requires `claim students`; refuses on inactive assignments; clears
  `instructor_id` **only if** the caller is the assigned instructor *or* has
  `manage students`; emails the student.
- **Update** — `@update` (`PUT /admin/training/assignments/{assignment}`):
  requires `manage students`; sets `instructor_id`, `active`, `status`,
  `training_type`; emails the student only when `notifyUser` is checked.
- **Deactivate (destroy)** — `@destroy` (`DELETE /admin/training/assignments`,
  id in the request body): allowed if the caller is the student *or* has the
  `deactivate training assignments` permission; sets `active = false` and
  `status = 'withdrawn'`.

- **Change status inline** — no route: the Status cell of the staff table is the
  `TrainingAssignmentStatus` Livewire component
  (`app/Livewire/TrainingAssignmentStatus.php`). Clicking the status badge opens
  a menu of statuses; picking one saves immediately via `setStatus()`. Requires
  `manage students` (re-checked in `mount()` and in `save()`, since a Livewire
  action request does not re-run the route middleware); everyone else sees a
  plain, non-clickable badge. Unlike `@update` it **never emails the student** —
  a one-click cell should not be able to send mail by accident — but the change
  is recorded in the activity log like any other assignment update. It changes
  `status` only: setting `FORFEIT` here does *not* also clear `active`, which is
  what `@destroy` does. Re-picking the status the assignment already has is a
  no-op (no write, no log entry).

  The menu is `position: fixed` with coordinates measured by Alpine, because the
  table lives in an `overflow-x-auto` wrapper that would clip a normally
  positioned dropdown. It is deliberately *not* teleported to `<body>` the way
  the neighbouring Actions menu is — a teleported element sits outside the
  Livewire component root, where `wire:` bindings stop working. On open it nudges
  itself back inside the viewport, flipping above the badge when there is no room
  below.

The staff list itself is server-rendered: `@index` paginates 20/page through
Scout and hands the results to the `training-assignment-table` Blade component.
`app/Livewire/TrainingAssignmentsTable.php` and its view are **dead code** —
nothing references either, and the view still renders a `NOT IMPL. YET` cell and
reads a `trainee_id` column the model does not have.

### Issue a solo certification

`SoloCertController@store` (`POST /admin/training/solo-certs`):

1. Validates `userId` (must exist) and `position` (regex above).
2. Rejects issuing a solo cert to yourself.
3. Creates the `SoloCert` with `issued_by_id = Auth::user()->id`.
4. Flips every active `TrainingAssignment` for that user to `TrainingStatus::SOLO`.
5. Dispatches `CreateVatusaSoloCert`.
6. Emails the student, bcc the issuer and `{vatusa_facility}-ta@vatusa.net`
   (`SoloCertIssued`).

### Revoke a solo certification

`SoloCertController@destroy` (`DELETE /admin/training/solo-certs/{id}`):

1. Requires the `revoke solo certs` permission (aborts 403 otherwise) — this is
   an instructor-only action even though the route sits in the `role:training`
   group.
2. Dispatches `RevokeVatusaSoloCert`.
3. Flips the user's active assignments back to `TrainingStatus::ACTIVE`.
4. Sets `revoked = true`.
5. Emails the student, bcc the issuer and `{vatusa_facility}-ta@vatusa.net`
   (`SoloCertRevoked`).

### VATUSA sync (tickets)

`SyncTrainingTickets` (dispatched on every ticket create, and by the dev-only
`GET /sync-training` route) is a **batch** job — it does not take a ticket
argument. On each run it loads *all* tickets with `vatusa_synced = false` and
POSTs each to
`{vatusa_api_url}/v2/user/{user_id}/training/record`, sending `duration`,
`session_date`, `position`, `movements`, `score`, `notes`, `location`, and the
`instructor_id`. On success it sets `vatusa_synced = true` and records the
returned `vatusa_id` (falling back to a hashed placeholder id if VATUSA's
response shape doesn't include one). Failures are logged and left unsynced for
the next run. See [docs/vatsim-integration.md](../vatsim-integration.md).

---

## Permissions & middleware

All admin training routes are grouped under `/admin` and then
`Route::prefix('/training')->middleware('role:training')` in `routes/web.php`,
so a user needs the Spatie **`training` role** to reach any of them. The one
exception is `training-assignment/create`, which sits outside that group and
only requires `auth` (any logged-in user can request training).

Individual actions layer on finer-grained permission checks
(`hasPermissionTo(...)`). Permissions are seeded in
`database/seeders/PermissionSeeder.php`:

| Role | Permissions (seeded) |
|------|----------------------|
| `training` | `create training tickets`, `edit training tickets`, `claim students`, `issue solo certs` |
| `instructor` | `revoke solo certs`, `manage training tickets`, `manage students` |

Per-action checks:

| Action | Check |
|--------|-------|
| Assignment update | `manage students` |
| Assignment claim | `claim students` |
| Assignment drop | `claim students` (+ must be the assigned instructor unless `manage students`) |
| Assignment destroy | student themselves, or `deactivate training assignments` |
| Solo cert revoke | `revoke solo certs` (403 if missing) |

Ticket create/update and solo cert issue rely on the `role:training` route
guard rather than an in-controller `hasPermissionTo` check.

---

## Background jobs & mail

### Jobs (`app/Jobs/`)

| Job | Trigger | What it does |
|-----|---------|--------------|
| `SyncTrainingTickets` | Ticket create; dev `/sync-training` route | Pushes all unsynced tickets to VATUSA, stamps `vatusa_synced` / `vatusa_id` |
| `SendTrainingRequestToWebhook` | Assignment create | Posts a Discord embed to `training_request_webhook_url` |
| `CreateVatusaSoloCert` | Solo cert issue | `POST {vatusa_api_url}/v2/solo` with cid, position, `expDate` (30 days out) |
| `RevokeVatusaSoloCert` | Solo cert revoke | `DELETE {vatusa_api_url}/v2/solo` with cid + position |

All four are queued (`ShouldQueue`). The VATUSA jobs read
`app.vatusa_api_url` / `app.vatusa_api_key` from config; never hard-code these.

### Mail (`app/Mail/`)

| Mailable | Sent when | Recipients | View |
|----------|-----------|------------|------|
| `TrainingTicketCreated` | Ticket created | student, bcc instructor | `mail.training-ticket-created` |
| `TrainingAssignmentCreated` | Assignment requested | requester | `mail.training-assignment-created` |
| `TrainingAssignmentUpdated` | Assignment claimed / dropped / updated | student, bcc instructor | `mail.training-assignment-updated` |
| `SoloCertIssued` | Solo cert issued | student, bcc issuer + `{facility}-ta@vatusa.net` | `mail.solo-cert-issued` |
| `SoloCertRevoked` | Solo cert revoked | student, bcc issuer + `{facility}-ta@vatusa.net` | `mail.solo-cert-revoked` |

---

## Key files

| File | Role |
|------|------|
| `app/Http/Controllers/Training/TrainingTicketController.php` | Ticket CRUD + sync dispatch |
| `app/Http/Controllers/Training/TrainingAssignmentController.php` | Assignment request / claim / drop / update / destroy |
| `app/Http/Controllers/Training/SoloCertController.php` | Solo cert issue / revoke |
| `app/Models/TrainingTicket.php` | Ticket model, `duration` attribute, sync flags |
| `app/Models/TrainingAssignment.php` | Assignment model, status/type casts |
| `app/Models/SoloCert.php` | Solo cert model, 30-day expiry accessors |
| `app/View/Components/TrainingAssignmentTable.php` | Staff assignment list (Blade, server-rendered) |
| `app/Livewire/TrainingAssignmentStatus.php` | Click-to-change status cell (`manage students`) |
| `app/Livewire/TrainingAssignmentsTable.php` | Dead code — unreferenced, superseded by the Blade table |
| `app/Enums/TrainingType.php` | Course types (incl. `MCO_*`) |
| `app/Enums/TrainingStatus.php` | Assignment lifecycle statuses |
| `app/Jobs/SyncTrainingTickets.php` | Batch push tickets → VATUSA |
| `app/Jobs/SendTrainingRequestToWebhook.php` | Discord notification on request |
| `app/Jobs/CreateVatusaSoloCert.php` | Push solo cert → VATUSA |
| `app/Jobs/RevokeVatusaSoloCert.php` | Revoke solo cert → VATUSA |
| `app/Mail/*` | The five training/solo mailables (table above) |
| `database/seeders/PermissionSeeder.php` | `training` / `instructor` role permissions |
| `routes/web.php` | Route definitions & middleware |

---

## Gotchas

- **`SyncTrainingTickets` is not per-ticket.** It takes no constructor argument
  and re-scans *every* unsynced ticket on each run. Dispatching it after a
  create will also (re)attempt any other tickets still pending.
- **The public "create" assignment route is really a store.** It's a `POST`
  named `training-assignment.create`, guarded only by `auth`, and it writes the
  record directly (there's a `TODO: make store` on it).
- **Ticket edit/update are dead code.** Both are annotated `!!!! NOT IN USE !!!!`
  and `destroy()` is empty — there is currently no supported way to edit or
  delete a ticket through the UI, and synced tickets are blocked from edits
  regardless.
- **Solo cert expiry is fixed at 30 days** and computed on the model; there's no
  column or config to change it. `expDate` sent to VATUSA is derived from the
  same accessor.
- **Assignment `status` values.** The controller sets both enum values
  (`TrainingStatus::SOLO`, `TrainingStatus::ACTIVE`) and a raw string
  (`'withdrawn'` on deactivate), while the model both casts `status` to the
  int-backed `TrainingStatus` enum *and* defines a `status()` mutator that
  lowercases the value. Be careful when reading/writing status directly — the
  cast and the mutator interact, and `'withdrawn'` is not a `TrainingStatus`
  case.
- **VATUSA `vatusa_id` may be a placeholder.** If VATUSA's response doesn't
  expose a record id in any of the shapes the job checks, it stores a truncated
  SHA-1 hash instead of a real VATUSA record id.
