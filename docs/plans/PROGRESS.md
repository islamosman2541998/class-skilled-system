# Progress

## Stage 0 — Foundation (complete, waiting for design approval)

Plan: `docs/plans/2026-11-12-stage-0-foundation.md`. All 17 tasks are committed, one commit per task.

### Delivered

- Login with throttling, idle timeout, deactivated-user sign-out, profile, language switch per user.
- Bilingual layout (RTL Arabic default, LTR English), Bootstrap 5.3 Sass builds per direction, Cairo / Tajawal loaded on demand, theme colours from settings.
- Roles and permissions registry, five default roles with record scope, `super-admin` bypass.
- Standard `DataTable` (Livewire): search with literal `%`/`_`, select / multi / boolean / range filters, date range with presets, sort, page sizes, totals over the whole filtered result, URL state, column visibility, saved filters, selection, delete / delete all with guards, trash, restore, permanent delete, streamed XLSX export, mobile cards.
- Users, roles (permission matrix), managed lists (12 types, drag reorder), settings groups (company, appearance, finance, uploads, system), document numbering, activity log (global screen and per-record tab).
- Upload component: FilePond with chunked, resumable uploads, client-side image resize, video poster captured in the browser, real-content type check on the server, private disk, WebP thumbnails, attachments grid with preview, rename and delete.
- In-app notifications: 12 types with configurable days and recipient roles, dedupe per record, topbar bell, list page, settings screen.
- Periodic tasks without cron (`RunDueTasks` after the response, `php artisan app:due-tasks` for hosts with cron): trash purge by retention, stale upload cleanup.
- Global search (`Ctrl+K`) with a provider registry; users provider now, each later module adds its own.
- Translation editor over every interface string, stored as database overrides.
- Home (unread notifications, own recent activity), reference screens `/design` and `/design/record`, `DemoSeeder`.

### Decisions taken

- Authorization is by permission strings through `Gate` (no Policy classes yet). Attachments check `{attachmentPermission()}.view|update` on the owning record. Modules with "assigned only" scope must add their record check when they are built (Stage 1).
- FilePond sends the original file name URL-encoded in `Upload-Name` on the start request, since browsers refuse non Latin-1 header values; chunk requests send only the offset.
- `App\Support\Like` is the single implementation of literal LIKE search, used by tables and search providers.
- Notification rules store only overrides; `NotificationType` holds the defaults.
- `lookups_listing_index (type, deleted_at, sort_order, id DESC)` added after the speed check.

### Speed check (MySQL 8.4, Laragon, Windows, 50,005 list rows)

Measured with `DB::listen` on the `LookupsTable` queries (count + page), two runs:

| Scenario | Before index | After index |
|---|---|---|
| Open list | 373–388 ms | 133–201 ms |
| Search | 333–419 ms | 150–219 ms |
| Search + status filter | 259–361 ms | 123–179 ms |
| Status filter + sort by name | 15–32 ms | 17–25 ms |

All scenarios are under the 300 ms target. The remaining cost is the `count(*)` of the paginator.

### Known gaps

- The manual 150 MB throttled upload with a cut connection was not run by hand; resume is covered by `ChunkUploadTest` and a 3 MB chunked upload with an Arabic name was verified in the browser against MySQL.
- `docs/screens/stage-0/` holds a partial set of screenshots (the in-app browser pane was hidden during the session). Overflow at 375 px was checked on every Stage 0 page in both languages by measuring `scrollWidth`; none overflow.
- The `.test` host did not resolve on this machine during the session; the app was run with `php artisan serve`. Reload Laragon (Menu → Apache → Reload, or restart) so it creates the `class-skilled-system.test` virtual host.
- Policies per module, the website API link, PDF (mPDF needs approval as a new package), stage and payment templates: Stage 1 and later.

### Next

Stage 1 plan (Sales: clients, requests, site visits, price items, quotations with PDF and WhatsApp, website link) — starts after the user approves the reference screens (spec §4.6).

## Stage 1 — Sales (in progress: Tasks 1–10 done, Task 11 gate partly done)

Plan: `docs/plans/2026-11-14-stage-1-sales.md`. Ledger of decisions: `.superpowers/sdd/2026-11-14-stage-1-sales/progress.md` (local, git-ignored).

Delivered: clients (contacts, duplicate phone detection across formats, quick add), requests with follow-ups and actions, record scope for "assigned only" roles (lists, pages, media, search, export, delete), site visits with the engineer phone screens, price items with Excel import, document and payment templates, quotations (server-side totals, revisions, copy, accept/reject with lock, reminders), PDF in Arabic / English / both with mPDF (Tajawal font), WhatsApp signed links, website request API and "fetch requests", sales demo data, default role permissions.

Gate run on MySQL: `migrate:fresh --seed` and `DemoSeeder` succeed; Pint and PHPStan clean; 360 tests pass; build succeeds; no log errors.

Left for Task 11: visual pass at 390 / 1440 px in English for the new screens, final self-review of Stage 1, then Stage 2 plan.

Known gaps: PDFs use Tajawal also when Cairo is chosen (user decision); the OFL licence text of Tajawal is not yet added next to the font files; the website project change is documented in `docs/integration/website-link.md`, not applied.
