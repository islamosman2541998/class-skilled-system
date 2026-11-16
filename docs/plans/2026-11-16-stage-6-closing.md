# Stage 6 — Closing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the system ready to run on the shared hosting: a daily database backup and an on-demand files backup with download and retention, a performance and permissions review backed by tests, an install command, and a deployment guide.

**Architecture:** Backups are a small `App\Services\Backup` module: a pure-PHP SQL dumper (no `mysqldump`, no system commands, §13) and a `ZipArchive` files archiver, logged in a `backups` table and run by the Stage 0 `DueTasks` after the response (§3.2). The reviews add tests that read the schema and the route list, so a later change that drops an index or an authorization check fails the suite. The install command wraps the first-run steps for a host without a terminal-friendly setup.

**Tech Stack:** As Stages 0–5. PHP `zlib` and `zip` extensions (present locally; the guide asks the host to confirm). No new package.

**Spec:** `docs/specs/2026-11-12-class-skilled-system-design.md` §13 (backups), §11 (performance), §12 (security), §3.2 (hosting limits), §14 (stage gate), §15 (Stage 6), §16 (points to confirm).

## Global Constraints

- Everything in `CLAUDE.md` → Global Constraints applies unchanged. No new composer or npm package.
- No system commands (`exec`, `shell_exec`, `proc_open`, `mysqldump`): the dump is built through PDO queries.
- Backups live on the private disk under `backups/`; they are downloaded only through an authorized controller (`settings.manage`), never linked publicly.
- Replies to the user in Arabic. Commit at the end of every task with the message shown. Local commits only.
- Out of this stage: restore from a backup inside the system (the guide documents a manual restore), off-site copies, new modules.

## Business rules this plan fixes

- The database backup runs once a day: a `DueTasks` task `backup-database` every 1440 minutes, after the response, under the existing lock. It can also be run from the backups page.
- A database backup is one gzip file `backups/db-YYYYMMDD-HHMMSS.sql.gz`: for each table, `DROP TABLE IF EXISTS`, the table's create statement (MySQL `SHOW CREATE TABLE`; SQLite `sqlite_master.sql`), and `INSERT` statements in chunks of 500 rows with values quoted through PDO. Foreign key checks are switched off at the top and back on at the end (MySQL).
- A files backup is one zip `backups/files-YYYYMMDD-HHMMSS.zip` of the private disk (without `backups/` and `tmp/`) and the public disk. It runs only from the backups page, after the response.
- Every run writes a `backups` row: type (`database` / `files`), status (`running` → `success` / `failed`), file path, size in bytes, error text, started / finished times, the user who started it (null for the daily run). A failed run notifies with `NotificationType::BackupFailed` (default role super-admin, already defined).
- Retention: `SystemSettings::backup_keep` (default 7) successful backups of each type are kept; older files and rows are removed after each successful run.
- The backups page (settings, `settings.manage`): last successful backup of each type, the list (date, type, size, status, error), "back up database now", "back up files now", download, delete.

## Review Focus

1. A backup started while another of the same type is running (double click, the daily task at the same moment) runs once; the second request is told a backup is already running. [Task 1]
2. A dump of a table with quotes, Arabic text, NULLs, and newlines restores to the same rows. [Task 1]
3. A backup file can never be downloaded by a user without `settings.manage`, nor by a crafted path outside `backups/`. [Task 2]
4. A route added later without an authorization check is caught by the route audit test. [Task 4]
5. The install command run twice changes nothing the second time and never overwrites an existing admin password. [Task 5]

---

### Task 1: Database backup

**Files:**
- Create: `app/Services/Backup/{SqlDumper,BackupRunner}.php`, `app/Models/Backup.php` + migration, `app/Enums/{BackupType,BackupStatus}.php`, settings migration (`system.backup_keep` 7)
- Modify: `app/Settings/SystemSettings.php` + system settings form and request, `app/Providers/AppServiceProvider.php` (or the provider that calls `DueTasks::registerDefaults`)
- Test: `tests/Feature/Backup/DatabaseBackupTest.php`

**Interfaces:**
- `SqlDumper::dump(string $path): int` — writes the gzip SQL file for the default connection (MySQL or SQLite), returns its size in bytes.
- `BackupRunner::run(BackupType $type, ?User $by = null): Backup` — takes a cache lock `backup:{type}` (refuses with `DomainException` translated when held), writes the running row, calls the dumper or the archiver (Task 2), marks success or failure (catching any `Throwable`, logging it, notifying), prunes to `backup_keep`.
- `DueTasks::register('backup-database', 1440, fn () => app(BackupRunner::class)->run(BackupType::Database))`.

- [ ] **Step 1: Failing tests** — `dumps every table into a gzip file and records a successful backup`, `the dump restores rows with quotes, Arabic text, NULLs and newlines` (dump, wipe the table, run the SQL through `DB::unprepared`, compare rows), `a second run while one is running is refused`, `a failed dump records the error and notifies super-admins`, `keeps only the configured number of successful backups`, `the daily task runs the database backup once a day`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-6: database backup`.

### Task 2: Files backup and the backups page

**Files:**
- Create: `app/Services/Backup/FilesArchiver.php`, `app/Http/Controllers/BackupController.php`, `resources/views/settings/backups.blade.php`
- Modify: `routes/web.php` (settings group: `settings.backups`, `.run`, `.download`, `.destroy`), `resources/views/settings/index.blade.php` (link), `lang/{ar,en}/settings.php`
- Test: `tests/Feature/Backup/BackupScreenTest.php`

**Interfaces:**
- `FilesArchiver::archive(string $path): int` — zips the private disk (without `backups/`, `tmp/`) and the public disk under `private/` and `public/` folders; returns the size.
- `BackupController@index`, `@run(Request, string $type)` (starts the run after the response with `dispatch(...)->afterResponse()`; `sync` queue), `@download(Backup $backup)` (streams the stored file; 404 when missing or the path is not under `backups/`), `@destroy(Backup $backup)` (deletes file and row). All need `settings.manage`.

- [ ] **Step 1: Failing tests** — `lists backups with the last successful of each type`, `back up files now archives the private and public disks without the backups folder`, `downloads a backup file and refuses a user without settings.manage`, `deletes a backup and its file`, `a backup row whose path leaves the backups folder is never served`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Page in the settings shell: two figures (last database backup, last files backup with their age), two buttons, the list as a plain table (newest first, 50 rows), the keep setting link to system settings.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-6: files backup and backups page`.

### Task 3: Performance review

**Files:**
- Create: `tests/Feature/Quality/IndexesTest.php`, `tests/Feature/Quality/QueryBudgetTest.php`, migration adding the indexes the audit finds missing
- Test: the two tests above

**Interfaces:**
- `IndexesTest` reads `Schema::getIndexes()` / `getForeignKeys()` for every application table and asserts an index covers each foreign key column, each `status`, `number`, and `*_date` column, and the `deleted_at` column of tables listed in big lists (projects, invoices, receipts, expenses, purchases, attendances, daily_reports, claims, leads, clients).
- `QueryBudgetTest` seeds 30 rows per module and loads each main list page and record page, asserting at most 35 queries per request (`DB::enableQueryLog`), so an N+1 regression fails.

- [ ] **Step 1: Write both tests** and run them — expected FAIL where indexes are missing or a page goes over budget (record each finding in the ledger).
- [ ] **Step 2: Fix** with one migration for the missing indexes and eager loading where a page went over budget.
- [ ] **Step 3: Measure** the 50,000-row lookups table search and filter locally on MySQL (`php artisan app:measure-table` printing milliseconds; target under 300 ms, spec §11) and record the result in `docs/plans/PROGRESS.md`.
- [ ] **Step 4: Run** the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-6: performance review`.

### Task 4: Permissions and security review

**Files:**
- Create: `tests/Feature/Quality/RouteAuthorizationTest.php`, `tests/Feature/Quality/UnescapedOutputTest.php`
- Modify: whatever the audit finds (controllers missing a check), `app/Http/Requests/Settings/AppearanceSettingsRequest.php` if colours are not validated as hex

**Interfaces:**
- `RouteAuthorizationTest`: every route outside `login`, `public.*`, the website API and `livewire.*` uses the `auth` middleware; every GET route without parameters, opened by a signed-in user without permissions, answers 403 or redirects to a page that does (allow list: home, profile, notifications, search, logout, locale); every route with parameters is resolved against seeded records and answers 403 or 404 for that user.
- `UnescapedOutputTest`: `{!! !!}` appears only in an allow list of Blade files (the theme partial, whose values are validated hex colours).

- [ ] **Step 1: Write both tests** and run them — expected FAIL wherever a route lacks a check (record each finding).
- [ ] **Step 2: Fix** each finding with the module's permission.
- [ ] **Step 3: Run** the suite — expected PASS.
- [ ] **Step 4: Commit** `stage-6: permissions and security review`.

### Task 5: Install command and deployment guide

**Files:**
- Create: `app/Console/Commands/InstallCommand.php` (`app:install`), `docs/deploy/shared-hosting.md`
- Modify: `.env.example` (production values commented), `docs/plans/PROGRESS.md`
- Test: `tests/Feature/InstallCommandTest.php`

**Interfaces:**
- `app:install` — checks PHP version and the `zip`, `zlib`, `gd`, `bcmath`, `intl`, `pdo_mysql` extensions, refuses without `APP_KEY`, runs `migrate --force`, seeds roles, lookups, document sequences and the admin from `ADMIN_*` (only when no admin exists), links storage when symlinks work, caches config, routes and views; prints each step. Running it again changes nothing.
- Guide: requirements, building assets locally (`npm run build`) and what to upload, the `public` folder as document root (or the subdomain of §16), `.env` production values (`APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, `SESSION_DRIVER=file`, mail), running `app:install` from the hosting terminal, the optional cron line (`* * * * * php artisan app:due-tasks`), the website link settings (`docs/integration/website-link.md`), backups and a manual restore (`gunzip` + import through phpMyAdmin), updating to a new version, the §16 points to confirm.

- [ ] **Step 1: Failing test** — `install runs migrations and seeds once and keeps an existing admin password`, `install refuses without an app key`.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement** the command and write the guide.
- [ ] **Step 4: Stage gate** — `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G && php artisan test && npm run build`; `migrate:fresh --seed` and `DemoSeeder` on MySQL only after the user agrees (also pending from Stages 4 and 5); visual pass at 375 px and 1440 px only with a signed-in browser session.
- [ ] **Step 5: Final self-review** against the Review Focus; fix Critical / Important with a failing test first.
- [ ] **Step 6: Update `docs/plans/PROGRESS.md`**; commit `stage-6: install command and deployment guide`.
