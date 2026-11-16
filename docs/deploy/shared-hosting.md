# Deploying to the shared hosting

Spec §3.2, §13, §15 (Stage 6). The system runs on the same kind of shared hosting as the website: no queue
worker, no cron needed, no FFmpeg, GD for images. Everything heavy runs after the response is sent.

## 1. What the server needs

- PHP 8.2 or newer with the extensions `bcmath`, `gd`, `intl`, `mbstring`, `pdo_mysql`, `zip`, `zlib`,
  `fileinfo`, `openssl`. `php artisan app:install` checks them and names any that is missing.
- MySQL 8 (or MariaDB 10.6+) with an empty database and a user that owns it.
- A terminal on the hosting (cPanel → Terminal, or SSH) to run the install command once and after each update.
  Ask the host to enable it if the plan does not show it.
- A subdomain for the system (spec §16, for example `system.class-skilled.com`) whose document root is the
  project's `public` folder. If the panel cannot point a subdomain inside the project, upload the project one
  level above `public_html/system` and set that subdomain's root to the project's `public` folder.

## 2. Build on your computer

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Upload the whole project **without** `node_modules`, `.env`, `storage/app/private/*`, `storage/logs/*` and
`tests`. The `public/build` folder must be uploaded: the server never runs npm.

## 3. The `.env` on the server

Copy `.env.example` to `.env` on the server and set at least:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://system.class-skilled.com
APP_LOCALE=ar
LOG_LEVEL=warning

DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=sync
CACHE_STORE=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=465
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@class-skilled.com

ADMIN_NAME="مدير النظام"
ADMIN_EMAIL=...
ADMIN_PASSWORD=...        # used only when the administrator is first created

BACKUP_DAILY=true
```

Then, in the hosting terminal, from the project folder:

```
php artisan key:generate
php artisan app:install
```

`app:install` migrates the database, adds the roles, lists, document numbers and the first administrator,
links `public/storage`, and caches config, routes and views. Running it again is safe: nothing is duplicated and
the administrator's password is never replaced. After the first sign-in, remove `ADMIN_PASSWORD` from `.env`.

If the host refuses symbolic links, `app:install` says so. Then copy `storage/app/public` into `public/storage`
after each upload of branding images, or ask the host to allow `storage:link`.

## 4. Folders that must be writable

`storage/` (all of it) and `bootstrap/cache/`. On most panels: permissions 755 for folders and 644 for files.

## 5. Periodic work

Nothing has to be scheduled. The notifications, trash purge, temporary upload cleanup and the daily database
backup run on the first signed-in request after they are due, after the page is sent, under a lock. If the host
offers cron, one line makes them run on time even when nobody signs in:

```
* * * * * cd /home/USER/system && php artisan app:due-tasks >> /dev/null 2>&1
```

## 6. Backups

- Settings → Backups shows the last database and files backups, runs either one now, downloads and deletes.
- The database is backed up once a day (`backups/db-YYYYMMDD-HHMMSS.sql.gz` on the private disk); files are backed up
  by hand (`backups/files-YYYYMMDD-HHMMSS.zip`). Settings → System → "Backups kept" sets how many of each stay.
- A failed backup notifies the system administrator.
- Download a copy regularly and keep it off the server; the hosting is not a backup of itself.

**Manual restore of the database:** download the `.sql.gz`, unzip it on your computer (7-Zip or
`gunzip file.sql.gz`), then in phpMyAdmin choose the empty database → Import → the `.sql` file. For a large file,
split it or ask the host to import it. **Files:** unzip the files backup; put `private/*` into `storage/app/private/`
and `public/*` into `storage/app/public/`.

## 7. Website link

The website side of the link is described in `docs/integration/website-link.md`. Generate the key in
Settings → Website link and put it in the website's `.env` together with the system URL.

## 8. Updating to a new version

1. Download a database and files backup from Settings → Backups.
2. Build again on your computer (section 2) and upload the changed files, including `public/build`.
3. In the terminal: `php artisan app:install` (runs new migrations and refreshes the caches).
4. Sign in and open a few pages; check `storage/logs/laravel.log` for errors.

## 9. Points to confirm (spec §16)

- The hosting is the same shared plan as the website and has no cron (the system works either way).
- The subdomain name of the system.
- The company TRN and bank details, entered in Settings → Company after the first sign-in.
- Whether projects are billed by claims; if not, choose "installments" when creating projects and the claims screens
  stay unused.

## 10. Known items before going live

- The Tajawal font's SIL Open Font Licence text should be placed next to the font files (`resources/fonts/pdf/`)
  when the fonts are distributed; download it from the font's Google Fonts page.
- Starting role permissions apply to new installs; on an existing install, review roles in Settings → Roles after
  an update that adds modules.
