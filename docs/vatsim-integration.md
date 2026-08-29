# VATSIM / VATUSA Integration

This document is the single reference for every way the ZJX ARTCC site talks to
the outside world: the VATUSA API, the VATSIM data feed, Discord, and outbound
email. If you are adding, debugging, or reasoning about an external call, start
here.

All external work happens through queued jobs (`app/Jobs/`), a set of data
transfer objects that parse API responses (`app/DTOs/`), and mailables
(`app/Mail/`). Configuration lives in `config/app.php`, `config/logging.php`,
and `config/mail.php`.

> Note on secrets: every credential is read from an environment variable. This
> document names those variables but never contains their values. Never paste an
> API key, SMTP password, or webhook URL into source or docs.

## Configuration keys

`config/app.php` exposes the integration settings the jobs read via
`config('app.*')`:

| Config key | Env var | Purpose |
| --- | --- | --- |
| `vatusa_api_url` | `VATUSA_API_URL` | Base URL of the VATUSA API (e.g. the public `https://api.vatusa.net`). |
| `vatusa_api_key` | `VATUSA_API_KEY` | Facility API key; sent as the `apikey` parameter on every VATUSA request. |
| `vatusa_facility` | `VATUSA_FACILITY` | Facility identifier, `ZJX`. Interpolated into facility-scoped endpoints and used in a few email subjects/recipients. |
| `vatsim_api_url` | `VATSIM_API_URL` | Base URL of the VATSIM API used for the online-controller feed. |
| `vatsim_auth_url` | `VATSIM_AUTH_URL` | VATSIM Connect OAuth base (covered in the auth doc, not here). |
| `vatsim_client_id` / `vatsim_client_secret` | `VATSIM_CLIENT_ID` / `VATSIM_CLIENT_SECRET` | VATSIM Connect OAuth credentials (auth doc). |
| `training_request_webhook_url` | `TRAINING_REQUEST_WEBHOOK_URL` | Discord webhook URL for training-assignment notifications. |

The Discord log channel and mail transport read their own env vars, documented
in their sections below.

## Scheduled work

`routes/console.php` registers the recurring jobs:

- `SyncRoster` — every two hours.
- `UpdateOnlineControllers` — every minute.
- `SyncVatsimSessions` — daily at 04:00 for the current and prior month. It
  dispatches one `SyncVatsimMemberSessions` job per rostered controller.

`SyncTrainingTickets` is **not** scheduled. It only runs when dispatched
manually through the dev-only `/sync-training` route (see
[Dev-only manual triggers](#dev-only-manual-triggers)). The other outbound
VATUSA jobs (`AddUserToVisitingRoster`, `CreateVatusaSoloCert`,
`RevokeVatusaSoloCert`, `SendTrainingRequestToWebhook`) are dispatched inline
from controllers when the relevant action happens.

## VATUSA API

VATUSA is the source of truth for the roster and staff. The site both pulls
from it (roster/staff sync) and pushes to it (training records, solo certs,
visiting roster).

### SyncRoster — `app/Jobs/SyncRoster.php`

Runs every two hours. Implements `ShouldBeUnique`, so overlapping runs are
skipped. This job is **destructive and rebuilds local state from VATUSA** — do
not treat locally-edited roster/staff data as durable.

What it does, in order (`handle()`):

1. **`updateRoster()`**
   - `GET {vatusa_api_url}/v2/facility/{vatusa_facility}/roster/both?apikey=...`
   - Sets `rostered = false` on every currently-rostered user before
     processing, so anyone no longer returned by VATUSA ends up unrostered.
   - Wraps each returned record in a `VatusaRosterUser` DTO and calls
     `User::updateFromVatusa()` (upsert, sets `rostered = true`).
   - Afterward clears `operating_initials` for any user still `rostered = false`
     (hanging OIs).
2. **`updateStaffMembers()`**
   - `GET {vatusa_api_url}/v2/facility/{vatusa_facility}?apikey=...`
   - Calls `clearUserRoles()`, which loops every user and removes the non-core
     roles: `staff`, `admin`, `training`, `events`, `facilities`, `instructor`.
   - `Staff::truncate()` — the `staff` table is emptied.
   - Parses the response into a `VatusaFacilityInfoDTO` and rebuilds staff rows
     via `Staff::fromFacilityInfoDTO()`.
   - `assignRoles()` re-derives each user's roles from their `Staff.title_short`
     (ATM/DATM/WM get the full set; TA/ATA get admin+training+staff; EC gets
     events+staff; FE gets facilities+staff; INS gets instructor+training+staff;
     MTR gets training+staff).
3. In the `development` environment only, any user whose `first_name` is `Web`
   is force-granted all roles, `rostered = true`, `division = USA`,
   `facility = ZJX` (test-account convenience).

Failures in either HTTP call throw and are caught in `handle()`; the error is
logged (with the facility URL, environment, and exception class) and the job
returns without partially-applied guarantees beyond what already ran.

### SyncTrainingTickets — `app/Jobs/SyncTrainingTickets.php`

Pushes locally-created training tickets up to VATUSA. Selects every
`TrainingTicket` with `vatusa_synced = false` and, for each:

- `POST {vatusa_api_url}/v2/user/{ticket.user_id}/training/record` with body:
  `apikey`, `instructor_id`, `session_date` (formatted `Y-m-d H:i`),
  `duration`, `position`, `movements`, `score`, `notes`, `location`.
- On a non-successful response (or a thrown exception) it logs a warning/error
  and moves on; the ticket stays unsynced for the next run.
- On success it reads the returned VATUSA record id. It probes several possible
  response shapes (`data.id`, `data.recordID`, `data.record.id`, top-level
  `recordID`, top-level `id`). It then sets `vatusa_synced = true` and stores the
  id in `vatusa_id`. If no id could be extracted, it stores a deterministic
  12-char hash derived from the response body as a fallback so the ticket is
  still marked synced.

### AddUserToVisitingRoster — `app/Jobs/AddUserToVisitingRoster.php`

Constructed with a VATSIM CID (`userId`). Adds that controller to the facility's
visiting roster:

- `POST {vatusa_api_url}/v2/facility/{vatusa_facility}/roster/manageVisitor/{userId}`
  with `apikey`.
- Logs success or failure; does not throw.

Dispatched from `VisitFacilityController::approve()` when a visitor request is
approved.

### CreateVatusaSoloCert — `app/Jobs/CreateVatusaSoloCert.php`

Constructed with a `SoloCert` model. Pushes a solo certification to VATUSA:

- `POST {vatusa_api_url}/v2/solo` with `apikey`, `cid` (the cert's `user_id`),
  `position`, and `expDate` (formatted `Y-m-d`).
- Logs success or failure; does not throw.

Dispatched from `SoloCertController::store()`.

### RevokeVatusaSoloCert — `app/Jobs/RevokeVatusaSoloCert.php`

Constructed with a `SoloCert` model. Removes a solo certification from VATUSA:

- `DELETE {vatusa_api_url}/v2/solo` with `apikey`, `cid`, and `position`.
- Logs success or failure; does not throw.

Dispatched from `SoloCertController` when a solo cert is revoked/deleted.

### User::createFromVatusa() / updateFromVatusa() — `app/Models/User.php`

These two static methods are the write path for pulling a controller into the
local `users` table.

- **`updateFromVatusa(VatusaRosterUser $vatusaUser)`** — Upserts a `users` row
  keyed on `id` (the VATSIM CID). Maps DTO fields to columns: `first_name`,
  `last_name` (both `ucfirst`-normalized), `email`, `rating`, `joined_at`
  (facility join date), `division` hardcoded to `USA`, `facility`,
  `rostered = true`, and `discord_id`. Called per-user by `SyncRoster`.
- **`createFromVatusa(int $id)`** — Fetches a single controller on demand:
  `GET {vatusa_api_url}/v2/user/{id}?apikey=...`, throws if the request fails or
  the `data` key is missing, wraps the payload in a `VatusaRosterUser`, and
  delegates to `updateFromVatusa()`. Called from `Staff::fromFacilityInfoDTO()`
  (`app/Models/Staff.php`) when a staff member's CID is not already a local user.

## VATSIM data feed

### UpdateOnlineControllers — `app/Jobs/UpdateOnlineControllers.php`

Runs every minute. Refreshes the list of ZJX controllers currently online.

- `GET {vatsim_api_url}/v2/atc/online` (no API key).
- Loads the configured callsign prefixes from the `statistics_prefixes` table
  (`StatisticsPrefixes::pluck('name')`).
- `OnlineController::truncate()` — the `online_controllers` table is emptied
  first, then repopulated.
- Each entry is parsed into an `OnlineControllerDTO`; only controllers whose
  `callsign` starts with one of the configured prefixes are kept and written via
  `OnlineController::fromDTO()`.

Because the table is truncated and rebuilt each minute, `online_controllers`
always reflects the last successful poll.

### Controller statistics — `SyncVatsimSessions` / `SyncVatsimMemberSessions`

Controller-hours syncs use VATSIM's public member-session endpoint:
`GET {vatsim_api_url}/v2/members/{cid}/atc`. The monthly dispatcher queues an
independent job for every rostered controller, preventing a single controller's
long history from delaying the rest of the facility.

Each member job requests pages of 100 sessions, filters the returned sessions by
the configured `statistics_prefixes`, and writes every qualifying ATC session to
`controller_sessions`. It recalculates the requested month's hours by facility
level after its final page. One queued job processes one API
page. Staging and production share VATSIM's per-IP limit, so each environment
defaults to three page jobs per minute (`VATSIM_STATS_SYNC_RATE_LIMIT`), leaving
headroom for both environments' online-controller polls and other calls. Initial
member jobs and continuation pages are delayed evenly across that per-minute
budget, avoiding a burst of immediately released queue jobs. The endpoint
returns a total `count`; before the final request, the job reduces `limit` to
the remaining records, so it never sends an out-of-range `offset + limit`
combination. Because member-history pages are newest first, pagination stops as
soon as a page reaches before the requested month. Debug logs record each
dispatcher and page boundary, qualifying-session counts, and the recalculated
monthly hour totals.

## Data transfer objects — `app/DTOs/`

DTOs are thin classes that turn a raw decoded API response (an array) into typed
PHP properties. They perform no I/O themselves.

| DTO | Parses | Notes |
| --- | --- | --- |
| `VatusaRosterUser` | A single element of the VATUSA `/v2/facility/{id}/roster/both` response (also reused for `/v2/user/{id}`). | Maps `cid`, `fname`/`lname`, `email`, `facility`, `rating` (into the `ControllerRating` enum), join/activity/created/updated timestamps, `discord_id`, and the many `flag_*` booleans. A sample payload is included as a comment in the file. |
| `VatusaFacilityInfoDTO` | The `data` block of `/v2/facility/{id}`. | Extracts the staff CIDs (`atm`, `datm`, `ta`, `wm`, `ec`, `fe`) and the facility `roles` array (each `cid`/`role`/`created_at`). |
| `OnlineControllerDTO` | A single element of the VATSIM `/v2/atc/online` response. | Holds `id`, `callsign`, and `start` (as a `DateTime`). |
| `VisitingChecklistDTO` | The VATUSA visiting-eligibility check response. | Flattens the `data` block into eligibility booleans (home controller, needs basic, 60-day / 90-day / 50-hour checks, overall `visiting`). Handles a `null` payload by setting an `error` flag and `visitEligible = false`. A sample payload is included as a comment in the file. |
| `VatusaRole` | — | Effectively empty (namespace declaration only); no properties or logic. |

## Discord

There are two independent Discord mechanisms.

### 1. Discord log channel — `config/logging.php`

A custom log channel named `discord` is backed by
`marvinlabs/laravel-discord-logger` (`MarvinLabs\DiscordLogger\Logger`). It
reads its webhook from `LOG_DISCORD_WEBHOOK_URL` and honors
`LOG_DISCORD_IGNORE_EXCEPTIONS`. It logs at `debug` level and up.

This is a general-purpose logging sink: any `Log::channel('discord')` write, or
the `discord` channel being part of the active `LOG_STACK`, sends log records to
Discord. It is not tied to any specific domain event.

### 2. Training-request webhook — `app/Jobs/SendTrainingRequestToWebhook.php`

A direct webhook post, separate from logging. Constructed with a
`TrainingAssignment` and dispatched from
`TrainingAssignmentController::create()` when a new assignment is created. It
`POST`s a Discord embed to `training_request_webhook_url`
(`TRAINING_REQUEST_WEBHOOK_URL`) titled "New Training Assignment Created", with
the student's name and CID, the assignment id, the training type, and the
created-at timestamp.

## Mail

Mail is sent through Laravel's mailer (`symfony/mailer` is the underlying
transport library). The transport is environment-driven via `MAIL_MAILER`
(`config/mail.php`):

- **Production / staging:** ZeptoMail over SMTP — the `smtp` mailer configured
  with ZeptoMail's SMTP host and credentials (`MAIL_HOST`, `MAIL_PORT`,
  `MAIL_USERNAME`, `MAIL_PASSWORD`, etc.).
- **Local:** `log` (the default when `MAIL_MAILER` is unset; also the value in
  `.env.example`). Messages are written to the log instead of sent.
- **Tests:** `array` (set in `phpunit.xml`), so nothing leaves the process and
  mail can be asserted on.

`config/mail.php` also defines a `zeptomail` mailer entry
(`'transport' => 'zeptomail'`). See the discrepancies note at the end of the WP
return regarding this entry.

### Mailables — `app/Mail/`

All nine mailables are plain `Mailable`s that render a Blade view in
`resources/views/mail/`. Most are queued at the call site with
`Mail::to(...)->queue(...)`.

| Mailable | Trigger |
| --- | --- |
| `Welcome` | Sent only from the dev-only `/test-email` route. No production dispatch site exists. Subject includes `vatusa_facility`. |
| `TrainingTicketCreated` | `TrainingTicketController::store()` — to the student, bcc the instructor. |
| `TrainingAssignmentCreated` | `TrainingAssignmentController::create()` — to the acting (authenticated) user. (Also returned, not sent, by the `/test-email` route.) |
| `TrainingAssignmentUpdated` | `TrainingAssignmentController` update/claim/drop paths — to the student, bcc the instructor where present. |
| `SoloCertIssued` | `SoloCertController::store()` — to the user, bcc the issuer and `{vatusa_facility}-ta@vatusa.net`. |
| `SoloCertRevoked` | `SoloCertController` revoke path — to the user, bcc the issuer and `{vatusa_facility}-ta@vatusa.net`. |
| `VisitorRequestReceived` | `VisitFacilityController::store()` — to the requester, bcc the ATM/DATM addresses. |
| `VisitorRequestAccepted` | `VisitFacilityController::approve()` — to the requester, bcc the ATM/DATM addresses. |
| `VisitorRequestRejected` | `VisitFacilityController::deny()` — to the requester, bcc the ATM/DATM addresses. |

## Dev-only manual triggers

`routes/web.php` registers three helper routes gated to the `development` and
`local` environments (`App::environment('development', 'local')`). They exist to
exercise integrations without waiting for the scheduler:

- `GET /sync` — dispatches `SyncRoster` and `UpdateOnlineControllers`, returns
  `scheduled`.
- `GET /sync-training` — dispatches `SyncTrainingTickets`, returns `scheduled`.
  This is the only place that job is triggered.
- `GET /test-email` — sends the `Welcome` mailable to a hardcoded address and
  returns a `TrainingAssignmentCreated` instance (for previewing the rendered
  view).

These routes do not exist in production.
