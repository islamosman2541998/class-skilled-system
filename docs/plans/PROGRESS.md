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

## Stage 1 — Sales (complete)

Plan: `docs/plans/2026-11-14-stage-1-sales.md`. Ledger of decisions: `.superpowers/sdd/2026-11-14-stage-1-sales/progress.md` (local, git-ignored).

Delivered: clients (contacts, duplicate phone detection across formats, quick add), requests with follow-ups and actions, record scope for "assigned only" roles (lists, pages, media, search, export, delete), site visits with the engineer phone screens, price items with Excel import, document and payment templates, quotations (server-side totals, revisions, copy, accept/reject with lock, reminders), PDF in Arabic / English / both with mPDF (Tajawal font), WhatsApp signed links, website request API and "fetch requests", sales demo data, default role permissions.

Gate run on MySQL: `migrate:fresh --seed` and `DemoSeeder` succeed; Pint and PHPStan clean; 360 tests pass; build succeeds; no log errors.

Visual pass: every Stage 1 screen checked at 375 px in Arabic and English (no horizontal scroll, no raw translation keys); quotation form checked at 1440 px in English; PDFs inspected in Arabic and bilingual.

Final self-review (no separate reviewer) fixed: assigned-only users could link a hidden client by its id and so reveal it; client page figures counted records the user cannot see; searchable selects hid their placeholder. Deferred: the "see amounts and costs" permission of §6 is not implemented yet; the Tajawal OFL licence text is not yet shipped with the font.

Next: Stage 2 plan (projects) — done, see below.

Known gaps: PDFs use Tajawal also when Cairo is chosen (user decision); the OFL licence text of Tajawal is not yet added next to the font files; the website project change is documented in `docs/integration/website-link.md`, not applied.

## Stage 2 — Projects (complete)

Plan: `docs/plans/2026-11-14-stage-2-projects.md`. Ledger of decisions: `.superpowers/sdd/2026-11-14-stage-2-projects/progress.md` (local, git-ignored).

Delivered: stage templates per service, projects (team, record scope by manager or member, client page tab), stages with weights, dates, drag order and progress (weights normalised, late stages flagged), payment schedule from percentages with the last line taking the remainder and installments due by date or by stage, conversion of an accepted quotation into one project only, variation orders (signed amounts, approval adds to the contract value once and adds an installment), handover with month-end safe warranty and a handover record PDF in Arabic / English / both, maintenance requests (MR numbers, inside / outside warranty from the request date, assigned user and project team see them), periodic notifications (late stage, project ending, warranty ending, installment due), projects demo data, default role permissions.

Gate run on MySQL: `migrate:fresh --seed` and `DemoSeeder` succeed (18 projects, 6 variations, 3 handovers, 10 maintenance requests); Pint and PHPStan clean; 430 tests pass; build succeeds; no log errors.

Visual pass: projects list, every project tab, stage modal, variation form, maintenance list / page / form, stage templates checked at 375 px (no horizontal scroll) and 1440 px, Arabic and English.

Final self-review (no separate reviewer) fixed: the stages tab imported SortableJS by its bare name from an inline script, so drag ordering failed in the browser (now a Vite entry `resources/js/project-stages.js`); hidden table header labels widened the payments tab on phones (`.table-responsive` is now positioned); maintenance numbers wrapped in the list.

Known gaps: starting role permissions apply to new installs only (existing roles keep their set, as in Stage 1); "see amounts and costs" permission still deferred; the Tajawal OFL licence text is still to add.

Next: Stage 3 plan (finance) — done, see below.

## Stage 3 — Finance (complete)

Plan: `docs/plans/2026-11-14-stage-3-finance.md`.

Delivered: tax invoices (draft, issue with a number reserved once, cancel with or without a replacement), invoice from a due installment with the VAT split rounding to the installment amount, invoice PDF in Arabic / English / both and WhatsApp signed links, overdue notices, receipts with allocations over open invoices (rows locked, never more than the invoice or the receipt), advances, receipt cancel that restores invoice, installment and project figures, receipt PDF, expenses with VAT included / excluded / none and a quick phone entry with a receipt photo, project ledger (invoiced, collected, cost, profit, margin) behind the "see amounts and costs" (`finance.costs`) and "see profitability" (`finance.profitability`) permissions, reports as standard tables with filters, totals and Excel export (profitability with cost per category, receivables aging, collections, expenses by category and project, VAT summary, sales performance with conversion rate, late projects and stages, client statement with opening and running balance and its PDF, installments), role dashboards whose figures follow permissions and link to the list that produced them, with a period filter and a six-month revenue against cost chart, finance demo data, default role permissions.

Gate run on MySQL: `migrate:fresh --seed` and `DemoSeeder` succeed (14 invoices in every status, 5 overdue, 8 receipts with partial, full and advance allocations and one cancelled, a cancelled invoice with its replacement, 40 expenses); Pint and PHPStan clean; 524 tests pass; build succeeds; the only new log line is the expected warning of the website sync failure test.

Visual pass: every finance list, invoice page and form, receipt form with allocations, quick expense, every report and the dashboard checked at 375 px in Arabic and at 1440 px in English (no horizontal scroll, no console errors); invoice, receipt and statement PDFs inspected (bilingual).

Final self-review against the Review Focus (no separate reviewer): allocation locking, single issue, installment rounding, cancel rules and report period edges are each covered by tests. Fixed during the stage: report count columns no longer summed in the totals row; the client statement running balance is cast to decimal (MySQL returned a float); dashboard money values wrapped inside the currency code; receipt allocation and report project numbers wrapped on phones; the dashboard chart was rewritten as HTML so its text keeps its size on any screen.

Added beyond the plan: an installments report (the list behind the "due installments" figure), a request date range and a "follow-up today" filter on the leads list (the spec lists the request date filter), shared `Project::constrainLate`, `Quotation::constrainExpiring`, `Lead::constrainOverdueFollowUp` / `constrainFollowUpToday` so each figure and its list filter count the same rows.

Known gaps: starting role permissions apply to new installs only (existing roles keep their set); dashboards per role were checked by tests, visually only as the system administrator (demo users have random passwords); the client statement PDF prints all dates from the client page, a period PDF needs the `from` / `to` query; supplier statements (§9.2) wait for suppliers; "reports not sent" on the engineer dashboard arrives with daily reports in Stage 4; the Tajawal OFL licence text is still to add.

Next: Stage 4 plan.
