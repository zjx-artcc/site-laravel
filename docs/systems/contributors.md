# Contributors

## Purpose

This document describes the contributors page, which credits the people who built the
site, and the admin screen for adding people GitHub doesn't list. It is written for
developers and contributors working on either page.

## Key concepts

- **GitHub is the main source.** `ContributorsController::index()` reads the public
  contributor list for the `zjx-artcc/site-laravel` repository from the GitHub REST API,
  leaving out bots, and looks up each person's display name.
- **Manual entries fill the gaps.** `ManualContributor` rows add people GitHub doesn't
  list, or move someone into a different section. A manual entry can have a GitHub
  username, a display name, or both, plus a short note.
- **Sections:** `main` (site contributors), `fork` (credited from the project this one
  was forked from), `contributor`, and `beta` (testers). A GitHub contributor with a
  manual entry in a section other than `main` appears only in that section, not in
  `main`.
- **Responses are cached.** The contributor list and each user profile are cached for one
  hour. Adding or removing a manual entry clears the contributor-list cache.

## Data model

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint | PK |
| `github_username` | string, nullable, unique | Max 39 (GitHub's limit). |
| `display_name` | string, nullable | Max 100. Required when there's no username. |
| `section` | string | `main`, `fork`, `contributor`, or `beta`. |
| `note` | string, nullable | Max 100. Shown under the name. |
| timestamps | | |

## Flows and routes

| Route | Requirement | Action |
| --- | --- | --- |
| `GET /contributors` | `auth` | Public-facing contributors page. |
| `GET /admin/contributors` | `admin` prefix (`view dashboard`) + `manage contributors` | List manual entries. |
| `POST /admin/contributors` | same | Add a manual entry. |
| `DELETE /admin/contributors/{contributor}` | same | Remove a manual entry. |

`PermissionSeeder` grants `manage contributors` to the `admin` role.

## Key files

| Path | Role |
| --- | --- |
| `app/Http/Controllers/ContributorsController.php` | GitHub fetch, caching, section building. |
| `app/Http/Controllers/Admin/ManualContributorController.php` | Manual entry admin. |
| `app/Models/ManualContributor.php` | Model. |
| `resources/views/contributors/index.blade.php` | Contributors page. |
| `resources/views/admin/contributors/index.blade.php` | Admin screen. |
| `tests/Feature/ContributorsTest.php` | Feature tests. |

## Gotchas

- **GitHub requests are unauthenticated,** so they share GitHub's low anonymous rate
  limit. The one-hour cache keeps this well under the limit in normal use. If GitHub
  fails, the page shows manual entries only, and failed profile lookups fall back to the
  login name.
- **Only the first 100 contributors are fetched** (`per_page=100`, no pagination).
- **Profile caches aren't cleared on admin changes,** only the contributor list. A
  renamed GitHub profile can take up to an hour to show.
