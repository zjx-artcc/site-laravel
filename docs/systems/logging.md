# Logging

## Purpose

This document describes how the ZJX ARTCC site logs what it is doing, so that
site reliability can be monitored and staff actions can be traced after the
fact. It covers the three kinds of logging in the app and how to turn up the
detail when something needs investigating.

For the database-backed audit trail and its admin viewer, see
[audit-logging.md](audit-logging.md); this document covers the application log
and how the two fit together.

## Key concepts

The app writes three distinct kinds of log:

| Kind | Where it goes | What it answers |
| --- | --- | --- |
| **Job run logs** | Application log | Did the scheduled job run, how long did it take, and what did it do? |
| **Privileged action logs** | `activity_log` table **and** application log | Which staff member did what to whom, and from where? |
| **Debug logs** | Application log, only at `LOG_LEVEL=debug` | Why is a sync behaving the way it is? |

Two rules shape the design:

- **Success is logged, not just failure.** A job that only logs on error is
  indistinguishable from a job that never ran. Every scheduled job emits a
  summary line when it completes, even when it changed nothing.
- **Privileged actions are written twice.** The database trail powers the admin
  viewer; the application-log copy survives a database loss or rollback.

## Job run logging

`App\Jobs\Concerns\LogsJobRun` gives a job a consistent run log. A job that uses
it emits:

| Line | Level | When |
| --- | --- | --- |
| `<Job> started` | `debug` | `startRun()` |
| `<Job>: <message>` | `debug` | `runDebug()`, per phase |
| `<Job>: <message>` | `warning` | `runWarning()` |
| `<Job> completed` | `info` (overridable) | `finishRun()` |
| `<Job> aborted: <reason>` | `error` | `abortRun()` — a known bad outcome, e.g. an upstream 5xx |
| `<Job> failed: <message>` | `error` | `failRun()` — an exception, with class, origin and trace |

Every line of a run carries the same `run_id` (a UUID) plus `job` and
`duration_ms`, so one run can be isolated even when runs overlap:

```
grep '"run_id":"0193f0c2-…"' storage/logs/laravel.log
```

### Metrics

`countMetric('key')` increments a counter and `setMetric('key', $value)` sets
one; both are merged into the context of every subsequent line, and in
particular into the completion summary. That summary is the thing to watch for
reliability monitoring — for example, a Statsim run reports:

```
SyncStatsimSessions completed {"job":"App\\Jobs\\SyncStatsimSessions",
  "run_id":"…","duration_ms":812.4,"sessions_received":143,
  "sessions_stored":97,"skipped_unrostered":31,"skipped_foreign_prefix":15,
  "controllers_recomputed":22,"year":2026,"month":8}
```

### Instrumented jobs

| Job | Schedule | Completion level | Key metrics |
| --- | --- | --- | --- |
| `SyncRoster` | every 2 hours | `info` | `roster_size_reported`, `roster_size_stored`, `controllers_joined`, `controllers_departed`, `staff_positions` |
| `SyncStatsimSessions` | daily 04:00 (current + previous month) | `info` | `sessions_received`, `sessions_stored`, `skipped_*`, `controllers_recomputed` |
| `SyncTrainingTickets` | on demand | `info` | `tickets_pending`, `tickets_synced`, `tickets_rejected`, `tickets_errored` |
| `UpdateOnlineControllers` | every minute | `debug` | `controllers_online_network`, `controllers_online_facility` |

`UpdateOnlineControllers` overrides `jobRunCompletionLevel()` to `debug`
because it runs every minute; at `info` it would add ~1,400 lines a day saying
nothing happened. Its failures are still logged at `error`.

The single-shot VATUSA jobs (`AddUserToVisitingRoster`, `CreateVatusaSoloCert`,
`RevokeVatusaSoloCert`) do not use the trait — they are one API call each — but
they log structured context (`user_id`, `position`, `status`, `body`) rather
than interpolated strings.

## Privileged action logging

`App\Support\PrivilegedAction::record()` records a staff action that changes
someone else's standing at the facility. One call writes both trails:

```php
PrivilegedAction::record(PrivilegedAction::VISITOR_REQUEST_APPROVED, $visitRequest, [
    'cid' => $visitRequest->user_id,
    'operating_initials' => 'AB',
]);
```

- **`activity_log`** — `event` is the action name, `causer` is the authenticated
  user, `subject` is the model passed in, and `properties` holds
  `attributes` (the array you passed, rendered by the audit viewer and CSV
  export) and `context` (request metadata: `source`, `ip`, `route`, `method`).
- **Application log** — one `info` line, `privileged action: <action>`, with the
  same detail flattened into the log context.

### Recorded actions

| Constant | Action name | Recorded in |
| --- | --- | --- |
| `VISITOR_REQUEST_SUBMITTED` | `visitor.submitted` | `VisitFacilityController@store` |
| `VISITOR_REQUEST_APPROVED` | `visitor.approved` | `VisitFacilityController@approve` |
| `VISITOR_REQUEST_DENIED` | `visitor.denied` | `VisitFacilityController@deny` |
| `SOLO_CERT_ISSUED` | `solo-cert.issued` | `SoloCertController@store` |
| `SOLO_CERT_REVOKED` | `solo-cert.revoked` | `SoloCertController@destroy` |
| `TRAINING_ASSIGNMENT_UPDATED` | `training-assignment.updated` | `TrainingAssignmentController@update` |
| `TRAINING_ASSIGNMENT_CLAIMED` | `training-assignment.claimed` | `TrainingAssignmentController@claim` |
| `TRAINING_ASSIGNMENT_DROPPED` | `training-assignment.dropped` | `TrainingAssignmentController@drop` |
| `TRAINING_ASSIGNMENT_FORFEITED` | `training-assignment.forfeited` | `TrainingAssignmentController@destroy` |
| `CONTRIBUTOR_ADDED` | `contributor.added` | `ManualContributorController@store` |
| `CONTRIBUTOR_REMOVED` | `contributor.removed` | `ManualContributorController@destroy` |
| `STATISTICS_SYNC_QUEUED` | `statistics.sync-queued` | `StatisticsController@sync` |
| `ROSTER_USER_REMOVED` | `roster.user-removed` | `SyncRoster` (no causer — VATUSA dropped them) |

### Adding a new one

1. Add a constant to `PrivilegedAction` (lowercase, dot-namespaced — it is
   rendered verbatim as the action badge in the viewer).
2. Call `PrivilegedAction::record()` *after* the change has been persisted, so a
   failed action is not recorded as a successful one.
3. For deletions, snapshot the attributes **before** deleting; afterwards there
   is nothing left to describe.
4. Add a row to the table above.

## Debug logging

Debug output is off in normal operation. There are two ways to turn it on.

**Turn the whole log down to debug** — simplest, noisiest:

```dotenv
LOG_LEVEL=debug
```

**Add the dedicated `debug` channel** — keeps the main log readable and puts
debug output in its own short-retention file at `storage/logs/debug.log`:

```dotenv
LOG_STACK=daily,debug
LOG_DEBUG_DAYS=3
```

The `debug` channel is pinned at debug level regardless of `LOG_LEVEL`, so the
main channel can stay at `info` while debug detail is captured separately.

### What debug logging gives you

`SyncRoster` is the most heavily instrumented, since it is the sync most likely
to need investigating:

- `fetching roster from VATUSA` / `fetching facility info from VATUSA`, with the
  endpoint being called
- `roster membership diff`, with the full `joined_cids` and `departed_cids` lists
- `assigning staff roles`, per staff position, with CID and title
- `rostered role synced`

`SyncStatsimSessions` logs the prefixes and rostered-controller count it is
filtering against; `UpdateOnlineControllers` logs each facility controller found
online; `SyncTrainingTickets` logs each ticket ID and the VATUSA record ID it
came back with.

Remember to put `LOG_LEVEL` back to `info` afterwards — the debug channel's
3-day retention limits the damage, but `LOG_LEVEL=debug` on the main channel
does not rotate any faster.

## Channels

Configured in `config/logging.php`; the active set is `LOG_STACK`
(comma-separated) under the `stack` channel.

| Channel | Purpose |
| --- | --- |
| `single` | Everything to one file, `storage/logs/laravel.log`. Default. |
| `daily` | Rotating daily file, retained `LOG_DAILY_DAYS` (default 14). |
| `debug` | Rotating daily file at `storage/logs/debug.log`, always debug level, retained `LOG_DEBUG_DAYS` (default 3). |
| `discord` | Ships to a Discord webhook (`LOG_DISCORD_WEBHOOK_URL`). |
| `stderr` | For containerised deployments where logs are collected from the process. |

A production deployment that wants alerting without noise typically runs
`LOG_STACK=daily,discord` with `LOG_LEVEL=info`, and temporarily switches to
`LOG_STACK=daily,discord,debug` while investigating.

## Testing

`tests/Pest.php` provides two helpers:

- `captureLogs()` swaps the logger for a Monolog `TestHandler`, so tests assert
  on the records that actually came out — level, message and context — rather
  than on which facade method was called.
- `findLog($handler, $needle)` returns the first record whose message contains
  `$needle`, as `['level' => …, 'message' => …, 'context' => …]`.

```php
$logs = captureLogs();

(new SyncStatsimSessions(2026, 1))->handle();

$completed = findLog($logs, 'SyncStatsimSessions completed');
expect($completed['level'])->toBe('INFO');
expect($completed['context']['sessions_stored'])->toBe(1);
```

Coverage lives in `tests/Feature/JobRunLoggingTest.php` and
`tests/Feature/PrivilegedActionLoggingTest.php`.

## Key files

| Path | Role |
| --- | --- |
| `app/Jobs/Concerns/LogsJobRun.php` | Job run logging trait |
| `app/Support/PrivilegedAction.php` | Privileged action recorder (dual trail) |
| `config/logging.php` | Channel definitions, including `debug` |
| `routes/console.php` | Schedule definitions and dispatch logging |
| `tests/Pest.php` | `captureLogs()` / `findLog()` helpers |

## Gotchas

- **An audit-log write failure does not fail the action.** `PrivilegedAction`
  catches a `Throwable` from the `activity_log` write, logs it at `critical`,
  and lets the staff action succeed. Availability is preferred over a hard
  audit guarantee, and the application-log copy still records the action — but
  it does mean the database trail can have gaps. Grep for
  `Failed to write privileged action` to find them.
- **`source` is decided by route presence, not `runningInConsole()`.** A queue
  worker has a `request()` instance but no resolved route, and
  `runningInConsole()` is true under the test runner even for HTTP tests.
- **Metrics are merged into every line after they are set**, not just the
  summary, so a counter read from a mid-run debug line is a running total, not
  a final one.
- **`runDebug()` still costs something.** The context array is built even when
  the debug level is disabled, so avoid expensive computation in the arguments
  of a per-item debug call inside a hot loop.
- **Only `properties['attributes']` is rendered** by the audit viewer and CSV
  export. Request metadata under `properties['context']` is stored but only
  visible in the database.
