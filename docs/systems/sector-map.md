# Sector Map

## Purpose

This document describes the "Active Center Split" map: an interactive map of ZJX's center
sectors that shows which sectors are combined under which active sector, and lets
qualified controllers update it. It is written for developers and contributors working on
the map component or its data.

## Key concepts

- **Splits:** the airspace is drawn in three stratums, `high`, `low`, and `ultra`. Each
  split has its own set of sector polygons, loaded from GeoJSON in `public/geojson/`
  (`high.json`, `low.json`, `ultra.json`).
- **Assignments:** each sector polygon (`sector_id`) is either offline or assigned to an
  *active sector* (`active_sector_id`), meaning the controller working that sector also
  covers it. Each active sector gets a color, and offline sectors are gray.
- **The palette is shared.** The list of active sectors, and so their colors, is shared
  across all three splits, so a sector keeps its color when you switch maps. There are at
  most nine active sectors (one per color in `SectorMap::COLORS`).
- **Valid sector numbers** are 1 to 99.
- **Who can edit:** controllers rated C1 or above, and anyone with a row in the `staff`
  table (`SectorMap::canEdit()`). Everyone else, including guests, gets a read-only map.
- **Online controllers** from `online_controllers` are passed to the view alongside the
  map (see [roster & membership](roster-and-membership.md)).

## Data model

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | PK |
| `split` | string | `high`, `low`, or `ultra`. |
| `sector_id` | integer | The polygon. |
| `active_sector_id` | integer, nullable | The sector it's combined under. Offline sectors have no row. |

There's a unique index on `(split, sector_id)`, and the model has no timestamps.

## Flows

All of this happens in the `sector-map` Livewire component (`app/Livewire/SectorMap.php`):

1. **View:** `mount()` loads the saved assignments for the current split (default
   `high`) and builds the palette from every split. `setSplit()` switches maps.
2. **Edit:** `enableEdit()` turns on edit mode for users who pass `canEdit()`. In edit
   mode a user can:
   - add an active sector to the palette (`addNewSector()` / `addSector()`)
   - pick a palette entry, or "offline", as the brush (`selectSector()`)
   - click polygons to paint them (`assignSector()`)
   - clear every assignment on the split (`clearAll()`)
   - throw away unsaved changes (`resetAll()` / `cancelEdit()`).
3. **Multi-split edits:** switching splits in edit mode keeps the unsaved changes for the
   split you left (`pendingSectors`), so one session can change several splits.
4. **Save:** `save()` re-checks `canEdit()`. For every split touched in the session, it
   deletes that split's rows and writes the new assignments. It then leaves edit mode and
   reloads from the database.

The front end listens for the `sectors-updated`, `split-changed`, and `split-saved`
browser events to redraw the map.

## Where it appears

- The home page, in the "Active Center Split" card.
- The admin dashboard (`resources/views/admin/index.blade.php`).

## Key files

| Path | Role |
| --- | --- |
| `app/Livewire/SectorMap.php` | Component: load, edit, save, `canEdit()`, colors. |
| `resources/views/livewire/sector-map.blade.php` | Map rendering and edit panel. |
| `app/Models/CenterSector.php` | Model. |
| `public/geojson/high.json`, `low.json`, `ultra.json` | Sector polygons for each split. |
| `public/geojson/ultra-raw.json` | Raw ultra-high polygons. The view doesn't load it. |
| `database/migrations/*_create_center_sectors_table.php` | Table. |
| `tests/Feature/SectorMapTest.php` | Feature tests. |

## Gotchas

- **Saving replaces whole splits.** `save()` deletes and rewrites every split touched in
  the session. Two people editing the same split at once means the last save wins.
- **No history.** `CenterSector` isn't audited, so there's no record of who changed the
  split or when.
- **Sector numbers come from the GeoJSON.** A polygon's `sector_id` has to match a
  feature in the split's GeoJSON file to show up on the map. Changing airspace means
  updating those files.
- **Livewire state is client-held.** The component re-checks `canEdit()` in every action
  that changes data, and `hydrate()` normalizes the palette on each request. Keep that
  pattern for any new action.
