# Operating Initials (OI Matrix)

Operating initials (OIs) are the unique two-letter identifiers (`AA`–`ZZ`, 676 possible) a
controller uses on the radar scope. The **OI Matrix** is an admin page for seeing every OI at a
glance, assigning/unassigning them, and reserving OIs so they can never be handed out.

## Page

- Route: `GET /admin/oi-matrix` (`admin.oi-matrix`), gated by the `manage users` permission (the
  `admin` role) on top of the admin group's `view dashboard` permission.
- Linked from **Facility Admin → OI Matrix** in the admin navbar (desktop and mobile).
- Livewire component `App\Livewire\OiMatrix` (`resources/views/livewire/oi-matrix.blade.php`).

Each cell is one OI, coloured by state:

| State | Meaning |
| --- | --- |
| Home | Assigned to a controller whose `facility` equals `config('app.vatusa_facility')` |
| Visitor | Assigned to any other controller |
| Reserved | Listed in `reserved_operating_initials`; cannot be assigned |
| Vacant | Free to assign |

**Controller-first assignment:** the "Find a controller" box searches rostered controllers who
have no OI (by name or CID). Pick one and the matrix enters assign mode: clicking any vacant OI
assigns it after a confirmation prompt, and a "Use XX" button offers their first/last initials when free.
Reserved and in-use OIs can't be assigned this way. The matrix can also be opened already in
assign mode with `?controller=<cid>` (used by the "Assign OI" button on the users page);
ineligible CIDs are ignored.

Clicking a cell opens a side panel: vacant OIs can be assigned to a rostered controller without an
OI, or reserved with an optional reason (e.g. *In memory of Nicholas Ader* for `NA`); assigned OIs
can be unassigned; reserved OIs can be released. The search box dims cells that don't match an OI,
name, CID, or reservation reason.

## Reserved OIs

Stored in `reserved_operating_initials` (see [`docs/database.md`](../database.md)). An OI that is
currently assigned must be unassigned before it can be reserved, and reserving an OI that is
already reserved is refused (the existing reason is never silently overwritten).

**Auditing** (see [audit-logging.md](audit-logging.md)): assigning/unassigning an OI is recorded by
`User`'s `operating_initials` log option as an `updated` entry (causer = the admin, old → new
value). Reserving/releasing writes a `created` / `deleted` entry on the `ReservedOperatingInitial`
subject with the initials and reason, shaped so the admin audit log viewer and CSV export render
them.

`App\Rules\NotReservedOperatingInitials` enforces reservations everywhere an OI can be set:
the matrix, the user profile edit form (`UserController@update`), and visitor-request approval
(`VisitFacilityController@approve`, plus the `OperatingInitialsInput` Livewire field). Those paths
also require exactly two letters.

## Related behavior

- `SyncRoster` clears the OI of anyone no longer rostered. Reservations are independent of users
  and are not touched by the sync.
- `User::operating_initials` upper-cases on get/set and stores `null` (not `''`) when cleared, so
  the unique index doesn't collide on empty strings.
