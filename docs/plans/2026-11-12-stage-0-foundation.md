# Stage 0 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A running, bilingual Laravel application with login, roles, settings, the standard data table, the upload component, trash, activity log, notifications, global search and document numbering, so every later stage only adds business modules on top.

**Architecture:** Server-rendered Blade pages through thin MVC controllers; Livewire is used only for list tables, all of which extend one abstract `DataTable` component. Cross-cutting behaviour (permissions, lookups, numbering, uploads, notifications, search, due tasks) lives in small services with registries that later stages add to. Nothing depends on cron or a queue worker.

**Tech Stack:** PHP 8.3, Laravel 12, MySQL 8, Livewire 4, Bootstrap 5.3 (Sass, RTL + LTR builds), jQuery, Toastr, SweetAlert2, FilePond, flatpickr, Tom Select, SortableJS, Tabler Icons, spatie permission / activitylog / medialibrary / settings / translation-loader / simple-excel, Pest, Pint, Larastan.

**Spec:** `docs/specs/2026-11-12-class-skilled-system-design.md` (Arabic). Section numbers below (§) refer to it.

**Project root:** `C:\laragon\www\class-skilled-system`. All paths are relative to it. Never write inside `C:\laragon\www\Hulul-eg`.

## Global Constraints

- Package versions (same as the working website project `C:\laragon\www\class-skilled`): `laravel/framework ^12.0`, `livewire/livewire ^4.4`, `spatie/laravel-permission ^8.3`, `spatie/laravel-activitylog ^4.12`, `spatie/laravel-medialibrary ^11.23`, `intervention/image ^3.0`, `spatie/laravel-settings ^3.10`, `spatie/laravel-translation-loader ^2.8`, `spatie/simple-excel ^3.11`, `pestphp/pest ^4.7`, `larastan/larastan ^3.13`.
- No other composer or npm package without asking the user first.
- Shared hosting: `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, `SESSION_DRIVER=file`. No cron, no queue worker, no FFmpeg, GD only, media conversions `->nonQueued()`.
- Livewire only inside table components. Pages and forms are Blade + controllers + Form Requests.
- No hardcoded user-facing text in Blade, PHP or JS. Every string is a `lang/ar` + `lang/en` key. JS reads strings from `window.I18N`.
- UI text rules (§4.2): no emoji, no exclamation marks, no greeting or marketing sentences; labels are a noun or a verb; messages are short and specific.
- Visual rules (§4.2): no gradients, no glow, no heavy shadows, no decorative icons; Tabler Icons outline only; body text 14–15px, headings max 22px; radius 8px controls / 10px panels.
- Direction by logical properties only (`ms-`, `me-`, `ps-`, `pe-`, `text-start`, `text-end`). Physical `left`/`right` only inside `<bdi dir="ltr">` for phones and numbers.
- Western digits (0-9) in both languages. Money through `money()`, dates through `fmt_date()`.
- Bilingual data columns are `name_ar` (required) and `name_en` (nullable, falls back to Arabic).
- Every delete is a soft delete. Every controller action and Livewire action is authorized.
- Arabic is the default locale and the fallback locale.
- Livewire 4 reference: a working v4 table trait exists at `C:\laragon\www\class-skilled\app\Livewire\Admin\Concerns\WithDataTable.php`. Read it for attribute and pagination usage before writing Task 6. Do not copy its Tailwind views.
- Commit at the end of every task with the message shown. Local commits only, never push.

## Review Focus

Each line has a pinning test in the task named in brackets.

1. Search text containing `%` or `_` is matched literally, not as a wildcard. [Task 6]
2. "Delete all" removes every row matching the active filter across all pages, and only those; guarded rows and the acting user are skipped and counted. [Tasks 7 and 8]
3. A chunked upload interrupted mid-file resumes from the stored offset and the assembled file is byte-identical. [Task 13]
4. A file named `.jpg` whose content is not an image is rejected on the server and its temp data removed. [Task 13]
5. A document number taken inside a transaction that rolls back is not consumed, and a year change restarts the counter. [Task 11]

## Deferred out of Stage 0

- mPDF and the quotation PDF reference template (§4.6) — first task of Stage 1.
- Stage, payment and document templates (§7) — Stages 1 and 2 with their modules.
- Website link (§10) — Stage 1. Backups (§13) — Stage 6. Dashboard figures (§9) — Stage 3.

## File Structure

```
app/
  Actions/            DeleteRecords, AttachUploads
  Contracts/          GuardsDeletion
  Enums/              LookupType, DocumentType, NotificationType, UploadKind
  Http/Controllers/   Auth/LoginController, HomeController, ProfileController, LocaleController,
                      UserController, RoleController, LookupController, SettingsController,
                      NumberingController, ActivityController, NotificationController,
                      NotificationSettingsController, UploadController, MediaController,
                      SearchController, TranslationController, DesignController
  Http/Middleware/    SetLocale, EnsureUserIsActive, IdleTimeout, RunDueTasks
  Http/Requests/      one per write action
  Livewire/           DataTable (abstract)
  Livewire/Tables/    UsersTable, RolesTable, LookupsTable, ActivityTable, NotificationsTable
  Models/             User, Lookup, DocumentSequence, TablePreference, SavedFilter, NotificationDispatch
  Models/Concerns/    HasLocalizedName, LogsModelActivity, HasAttachments
  Notifications/      SystemNotification
  Search/             SearchProvider, SearchResult, SearchRegistry, UserSearchProvider
  Services/           NumberGenerator, TableExporter, Notifier, DueTasks, ActivityLogger
  Services/Uploads/   TemporaryUpload, ChunkReceiver, UploadValidator
  Settings/           CompanySettings, AppearanceSettings, FinanceSettings, UploadSettings,
                      SystemSettings, NotificationSettings
  Support/            helpers.php, Theme, Menu, PermissionRegistry, TrashRegistry, LookupUsage,
                      TranslationCatalog
  Tables/             Column, DeleteResult, Filters/{Filter,SelectFilter,MultiSelectFilter,BooleanFilter,NumberRangeFilter}
resources/
  scss/               app-ltr.scss, app-rtl.scss, _app.scss, _tokens.scss, _layout.scss, _components.scss, _mobile.scss
  css/                font-cairo.css, font-tajawal.css
  js/                 app.js, uploader.js, ui/{toast,confirm,forms,select,datepicker,sidebar,search,notifications}.js
  views/              layouts/{app,auth}.blade.php, partials/, components/, livewire/data-table.blade.php,
                      auth/, home, profile/, users/, roles/, lookups/, settings/, activity/, notifications/, design/
lang/{ar,en}/         app, auth, table, users, settings, notifications, js, validation, pagination, passwords
tests/                Feature/, Unit/, Fixtures/{Widget, WidgetsTable, migration}
```

---

### Task 1: Project scaffold and quality gates

**Files:**
- Create: Laravel skeleton at project root, `phpstan.neon`, `CLAUDE.md`, `README.md`, `.env.example`
- Modify: `app/Providers/AppServiceProvider.php`, `composer.json` (autoload `app/Support/helpers.php`), `phpunit.xml`, `tests/TestCase.php`, `tests/Pest.php`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Produces: a booting app, sqlite `:memory:` test database, `tests/TestCase.php` calling `$this->withoutVite()` in `setUp()`, empty `app/Support/helpers.php`.

- [ ] **Step 1: Create the skeleton next to the existing `docs/` folder**

```bash
cd /c/laragon/www/class-skilled-system
composer create-project laravel/laravel _tmp "^12.0"
```
Move everything from `_tmp/` (including dotfiles) into the root, then delete `_tmp/`. `docs/` must remain untouched.

- [ ] **Step 2: Install packages**

```bash
composer require livewire/livewire:^4.4 spatie/laravel-permission:^8.3 spatie/laravel-activitylog:^4.12 spatie/laravel-medialibrary:^11.23 intervention/image:^3.0 spatie/laravel-settings:^3.10 spatie/laravel-translation-loader:^2.8 spatie/simple-excel:^3.11
composer require --dev pestphp/pest:^4.7 pestphp/pest-plugin-laravel:^4.1 larastan/larastan:^3.13
```
Publish the migrations and config of permission, activitylog, medialibrary, settings and translation-loader.

- [ ] **Step 3: Configure the environment**

`.env` and `.env.example`: `APP_NAME="Class Skilled System"`, `APP_URL=http://class-skilled-system.test`, `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=ar`, `DB_CONNECTION=mysql`, `DB_DATABASE=class_skilled_system`, `DB_USERNAME=root`, empty password, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, plus empty `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` keys in `.env.example`. Add a `private` disk (`storage/app/private`) to `config/filesystems.php`.

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS class_skilled_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

- [ ] **Step 4: Write the smoke test**

```php
it('boots in Arabic', fn () => expect(config('app.locale'))->toBe('ar')->and(config('app.fallback_locale'))->toBe('ar'));
it('uses the sync queue and file cache', fn () => expect(config('queue.default'))->toBe('sync'));
```
`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`.

- [ ] **Step 5: App defaults**

`AppServiceProvider::boot()`: `Model::preventLazyLoading(! app()->isProduction())`. `phpstan.neon`: larastan extension, level 5, paths `app`. Remove the default welcome route and view.

- [ ] **Step 6: Project notes**

`CLAUDE.md`: the Global Constraints section above verbatim, the spec path, and "one stage plan at a time; plans live in `docs/plans/`". `README.md`: local setup commands only.

- [ ] **Step 7: Verify and commit**

Run: `php artisan migrate:fresh && php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse`
Expected: migrations run, 2 tests pass, Pint and PHPStan report no errors.

```bash
git init && git add -A && git commit -m "stage-0: project scaffold"
```

---

### Task 2: Settings groups and format helpers

**Files:**
- Create: `app/Settings/{Company,Appearance,Finance,Upload,System}Settings.php`, one settings migration per group in `database/settings/`
- Modify: `app/Support/helpers.php`, `config/settings.php` (register classes, enable cache)
- Test: `tests/Unit/FormatHelpersTest.php`, `tests/Feature/SettingsDefaultsTest.php`

**Interfaces:**
- Produces settings classes with these properties and defaults:
  - `CompanySettings` (group `company`): `name_ar='كلاس سكيلد للمقاولات العامة'`, `name_en='Class Skilled General Contracting'`, `trn=null`, `license_no=null`, `address_ar=null`, `address_en=null`, `phones=[]`, `email=null`, `bank_name=null`, `bank_account_name=null`, `bank_iban=null`, `logo_path=null`, `login_logo_path=null`, `stamp_path=null`, `signature_path=null`
  - `AppearanceSettings` (group `appearance`): `color_navy='#2B4168'`, `color_deep='#0C122B'`, `color_sky='#4FC3EE'`, `color_ink='#24324D'`, `color_muted='#5D6A80'`, `color_line='#E3E8EF'`, `color_paper='#F5F7FA'`, `primary_font='cairo'` (`cairo|tajawal`), `heading_font='same'` (`same|other`), `default_locale='ar'`
  - `FinanceSettings` (group `finance`): `currency_code='AED'`, `currency_symbol_ar='د.إ'`, `currency_symbol_en='AED'`, `decimals=2`, `vat_rate='5.00'`, `quotation_validity_days=30`, `invoice_due_days=14`
  - `UploadSettings` (group `uploads`): `image_max_mb=10`, `video_max_mb=200`, `file_max_mb=25`, `image_types=['jpg','jpeg','png','webp']`, `video_types=['mp4','webm']`, `file_types=['pdf','doc','docx','xls','xlsx','dwg','zip']`, `image_max_dimension=2000`, `chunk_size_mb=2`
  - `SystemSettings` (group `system`): `trash_retention_days=null`, `session_idle_minutes=120`, `public_link_hours=72`
- Produces helpers: `money(string|int|float|null $amount, bool $withCurrency = true): string`, `fmt_date(?\DateTimeInterface $date): string` (`d/m/Y`, empty string for null), `fmt_datetime(?\DateTimeInterface $date): string` (`d/m/Y H:i`).

- [ ] **Step 1: Write the failing tests**

```php
it('formats money in Arabic', fn () => expect(money('1234.5'))->toBe("1,234.50\u{00A0}د.إ"));
it('formats money in English', function () { app()->setLocale('en'); expect(money(1234.5))->toBe("1,234.50\u{00A0}AED"); });
it('omits the currency on request', fn () => expect(money('1234.5', false))->toBe('1,234.50'));
it('follows the decimals setting', function () { $s = app(FinanceSettings::class); $s->decimals = 3; $s->save(); expect(money(1, false))->toBe('1.000'); });
it('treats null as zero', fn () => expect(money(null, false))->toBe('0.00'));
it('formats dates with western digits', fn () => expect(fmt_date(Carbon::parse('2026-11-12')))->toBe('12/11/2026'));
it('has the spec defaults', fn () => expect(app(FinanceSettings::class)->vat_rate)->toBe('5.00')
    ->and(app(AppearanceSettings::class)->primary_font)->toBe('cairo'));
```

- [ ] **Step 2: Run** `php artisan test --filter="FormatHelpers|SettingsDefaults"` — expected: FAIL, classes and functions missing.
- [ ] **Step 3: Implement the five settings classes and their settings migrations** with the defaults above.
- [ ] **Step 4: Implement the three helpers** in `app/Support/helpers.php`; `money()` uses `number_format` with the `decimals` setting and the symbol for the current locale after a non-breaking space.
- [ ] **Step 5: Run the same filter** — expected: PASS.
- [ ] **Step 6: Commit** `git commit -am "stage-0: settings groups and format helpers"` (after `git add -A`).

---

### Task 3: Users and authentication

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`, `HomeController.php`, `ProfileController.php`, `app/Http/Middleware/{EnsureUserIsActive,IdleTimeout}.php`, `app/Http/Requests/{LoginRequest,UpdateProfileRequest,UpdatePasswordRequest}.php`, `resources/views/{layouts/auth,auth/login,home,profile/edit}.blade.php`, `lang/{ar,en}/{app,auth}.php`
- Modify: users migration, `app/Models/User.php`, `database/factories/UserFactory.php`, `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/ProfileTest.php`

**Interfaces:**
- Produces `users` columns: `name`, `email` (unique), `phone` (nullable), `password`, `locale` (default `ar`), `is_active` (default true), `last_login_at` (nullable), `remember_token`, timestamps, `deleted_at`.
- Produces routes: `login` (GET/POST `/login`, POST throttled `5,1`), `logout` (POST), `home` (GET `/`), `profile.edit` (GET `/profile`), `profile.update` (PUT), `profile.password` (PUT `/profile/password`). All except login are in the `auth` group with `EnsureUserIsActive` and `IdleTimeout`.
- No registration and no email password reset.

- [ ] **Step 1: Write the failing tests**

```php
it('redirects guests to login', fn () => get('/')->assertRedirect(route('login')));
it('logs in and stamps last_login_at', function () {
    $u = User::factory()->create(['password' => 'secret-pass-1']);
    post('/login', ['email' => $u->email, 'password' => 'secret-pass-1'])->assertRedirect(route('home'));
    expect($u->fresh()->last_login_at)->not->toBeNull();
});
it('rejects a wrong password', fn () => post('/login', ['email' => 'x@y.z', 'password' => 'nope'])->assertSessionHasErrors('email'));
it('rejects an inactive user with auth.inactive', function () { /* is_active=false → error message __('auth.inactive'), still guest */ });
it('throttles the sixth attempt in a minute', function () { /* 5 failures then assertStatus(429) */ });
it('logs out a user deactivated during the session', function () { /* actingAs, set is_active=false, get('/') → redirect login */ });
it('logs out after the idle limit', function () { /* session_idle_minutes=10, travel 11 minutes, get('/') → redirect login */ });
it('updates profile locale', function () { /* put profile.update locale=en → user.locale === 'en' */ });
it('requires the current password and 8 characters to change password', function () { /* wrong current → error; 7 chars → error; valid → Hash::check passes */ });
```

- [ ] **Step 2: Run** `php artisan test --filter="LoginTest|ProfileTest"` — expected: FAIL.
- [ ] **Step 3: Migration, model and factory.** `User` uses `SoftDeletes`; casts `is_active` bool, `last_login_at` datetime, `password` hashed.
- [ ] **Step 4: Controllers, requests, middleware and routes.** `IdleTimeout` stores `last_activity_at` in the session and compares against `SystemSettings::session_idle_minutes`. Views are plain Blade on a minimal `layouts/auth`; styling comes in Task 4.
- [ ] **Step 5: Run the same filter** — expected: PASS.
- [ ] **Step 6: Commit** `stage-0: users and authentication`.

---

### Task 4: Locale, asset pipeline and layout shell

**Files:**
- Create: `app/Http/Middleware/SetLocale.php`, `app/Http/Controllers/LocaleController.php`, `app/Support/{Theme,Menu}.php`, all files under `resources/scss`, `resources/css`, `resources/js` listed in File Structure except `uploader.js`, `search.js`, `notifications.js`; `resources/views/layouts/app.blade.php`, `resources/views/partials/{theme,sidebar,topbar,flash}.blade.php`, `resources/views/components/{page-header,panel,field,status-badge,empty-state}.blade.php`, `lang/{ar,en}/js.php`
- Modify: `vite.config.js`, `package.json`, `resources/views/layouts/auth.blade.php` and the Task 3 views (apply the layout), `bootstrap/app.php`
- Test: `tests/Unit/ThemeTest.php`, `tests/Feature/LocaleTest.php`, `tests/Feature/NoHardcodedTextTest.php`

**Interfaces:**
- Produces `Theme::cssVariables(): array<string,string>` — keys `--c-navy`, `--c-deep`, `--c-sky`, `--c-ink`, `--c-muted`, `--c-line`, `--c-paper`, `--bs-primary`, `--bs-primary-rgb`, `--font-body`, `--font-heading`. `--bs-primary` is `color_navy`.
- Produces `Theme::fonts(): list<string>` — families to load: `['cairo']`, `['tajawal']`, or both when `heading_font='other'`.
- Produces `Menu::items(User $user): array` — groups of `['label' => lang key, 'icon' => tabler class, 'route' => name, 'permission' => ?string]`, filtered by `$user->can()`; `Menu::register(string $group, array $item): void` for later stages.
- Produces route `locale.switch` (POST `/locale/{locale}`, `ar|en`) saving `users.locale`.
- Produces JS globals: `window.toast(type, message)`, `window.confirmAction({title, text, confirmText, danger, requireText}): Promise<boolean>`, `window.I18N`. Livewire event `toast` with `{type, message}` shows a toast.
- Produces HTML hooks: `form[data-dirty-check]` (unsaved-changes warning), `[data-confirm]` (confirm before submit), `select[data-select]` (Tom Select), `[data-date]` and `[data-date-range]` (flatpickr, Arabic locale in `ar`).
- Produces Blade components: `<x-page-header :title :breadcrumbs>` with `actions` slot, `<x-panel>`, `<x-field name label required>`, `<x-status-badge color>`, `<x-empty-state :message>` with action slot.

- [ ] **Step 1: Install front-end packages**

```bash
npm i bootstrap@^5.3 @popperjs/core jquery@^3.7 toastr@^2.1 sweetalert2@^11 flatpickr@^4.6 tom-select@^2.6 sortablejs@^1.15 @tabler/icons-webfont@^3 @fontsource/cairo@^5 @fontsource/tajawal@^5
npm i -D sass rtlcss
```

- [ ] **Step 2: Write the failing tests**

```php
it('maps settings to css variables', fn () => expect(Theme::cssVariables())->toMatchArray(['--c-navy' => '#2B4168', '--bs-primary' => '#2B4168', '--bs-primary-rgb' => '43, 65, 104']));
it('loads only the primary font', fn () => expect(Theme::fonts())->toBe(['cairo']));
it('loads both fonts when heading font is the other one', function () { /* heading_font=other → ['cairo','tajawal'] */ });
it('switches primary to tajawal', function () { /* primary_font=tajawal → ['tajawal'], --font-body contains Tajawal */ });
it('renders rtl for Arabic and ltr for English', function () { /* user locale ar → see '<html lang="ar" dir="rtl"', en → 'lang="en" dir="ltr"' */ });
it('saves the switched locale on the user', function () { /* post locale.switch en → users.locale = en */ });
it('rejects an unknown locale', fn () => actingAs(User::factory()->create())->post('/locale/fr')->assertNotFound());
it('has no Arabic or English literals in views', function () {
    // every file in resources/views: no \p{Arabic} characters, and no text node of 3+ Latin words outside {{ }}, @ directives and attributes
});
```

- [ ] **Step 3: Run** `php artisan test --filter="ThemeTest|LocaleTest|NoHardcodedText"` — expected: FAIL.
- [ ] **Step 4: Vite config.** Inputs: `resources/scss/app-ltr.scss`, `resources/scss/app-rtl.scss`, `resources/css/font-cairo.css`, `resources/css/font-tajawal.css`, `resources/js/app.js`. Both scss entries only `@use 'app'`. Add an inline PostCSS plugin that runs `rtlcss` when `result.opts.from` contains `app-rtl`.
- [ ] **Step 5: Sass.** `_tokens.scss` overrides Bootstrap variables (radius 8px, font sizes 14.5px base, spacing) and defines custom properties with the spec defaults; component styles read `var(--c-*)` so runtime settings win. `font-*.css` import weights 400, 500, 700 with Arabic and Latin subsets, `font-display: swap`.
- [ ] **Step 6: `Theme`, `Menu`, `SetLocale`, `LocaleController`.** Locale order: `users.locale`, then `AppearanceSettings::default_locale`.
- [ ] **Step 7: Layout and partials.** `layouts/app`: `<html lang dir>`, the CSS entry for the direction, font entries from `Theme::fonts()`, `partials/theme` printing `:root{...}`, sidebar on the start side (collapsible groups, collapses to icons, state in `localStorage`), topbar (search trigger, quick-add slot hidden when empty, locale switch, notifications slot, user menu), flash-to-toast partial, `@stack('scripts')`.
- [ ] **Step 8: JS modules** as listed in Interfaces. `forms.js` also disables submit buttons on submit.
- [ ] **Step 9: Run tests and build**

Run: `php artisan test --filter="ThemeTest|LocaleTest|NoHardcodedText" && npm run build`
Expected: PASS; then confirm `grep -o "\.ms-3{margin-right" public/build/assets/app-rtl-*.css` prints a match and the same grep on `app-ltr-*.css` prints nothing.

- [ ] **Step 10: Look at it.** Open `/login` and `/` at 390px and 1440px in both languages; no horizontal scroll, sidebar on the correct side, fonts applied.
- [ ] **Step 11: Commit** `stage-0: locale, asset pipeline and layout`.

---

### Task 5: Roles and permissions

**Files:**
- Create: `app/Support/PermissionRegistry.php`, migration adding `label_ar`, `label_en`, `scope`, `is_system` to `roles`, `database/seeders/{RolesSeeder,AdminUserSeeder}.php`
- Modify: `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/PermissionsTest.php`

**Interfaces:**
- Produces `PermissionRegistry::ACTIONS = ['view','create','update','delete','restore','force-delete','export','print']`.
- Produces `PermissionRegistry::register(string $module, array $actions = self::ACTIONS): void`, `::modules(): array<string, list<string>>`, `::all(): list<string>` (names `"{module}.{action}"`), `::sync(): void` (creates missing permissions, idempotent).
- Stage 0 registrations: `users`, `roles`, `lookups` (all actions), `settings` → `['manage']`, `activity` → `['view']`, `translations` → `['manage']`.
- Produces roles (name / label_ar / scope): `super-admin` / مدير النظام / all (is_system), `management` / الإدارة / all, `sales` / مبيعات / all, `engineer` / مهندس مشروع / assigned, `accountant` / محاسب / all.
- Produces `User::seesAllRecords(): bool` — true when any of the user's roles has `scope='all'`.
- `Gate::before`: `super-admin` passes everything.
- `AdminUserSeeder` creates one `super-admin` from `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`; it throws when they are empty outside the testing environment.

- [ ] **Step 1: Write the failing tests**

```php
it('syncs every registered permission once', function () { PermissionRegistry::sync(); PermissionRegistry::sync(); expect(Permission::where('name', 'users.force-delete')->count())->toBe(1)->and(Permission::count())->toBe(count(PermissionRegistry::all())); });
it('lets super-admin pass any gate', function () { /* user with super-admin → can('anything.at-all') true */ });
it('reports record scope from roles', function () { /* engineer → seesAllRecords false; engineer+sales → true */ });
it('hides menu items the user cannot view', function () { /* Menu::items(user without users.view) contains no route users.index */ });
it('seeds the five roles with Arabic labels', function () { /* role engineer: label_ar مهندس مشروع, scope assigned */ });
```

- [ ] **Step 2: Run** `php artisan test --filter=PermissionsTest` — expected: FAIL.
- [ ] **Step 3: Implement registry, migration, seeders, `Gate::before`, `HasRoles` on `User`.**
- [ ] **Step 4: Run** the filter — expected: PASS. Then `php artisan migrate:fresh --seed` with admin env values set locally.
- [ ] **Step 5: Commit** `stage-0: roles and permissions`.

---

### Task 6: DataTable core — search, filters, date range, sort, paging, totals

**Files:**
- Create: `app/Livewire/DataTable.php`, `app/Tables/Column.php`, `app/Tables/Filters/{Filter,SelectFilter,MultiSelectFilter,BooleanFilter,NumberRangeFilter}.php`, `resources/views/livewire/data-table.blade.php`, `resources/views/livewire/data-table/{toolbar,filters,active-filters,row-card,pagination}.blade.php`, `lang/{ar,en}/table.php`, `tests/Fixtures/{Widget.php,WidgetsTable.php,create_widgets_table.php}`
- Test: `tests/Feature/DataTable/CoreTest.php`

**Interfaces:**
- Fixture `widgets` columns: `name_ar`, `name_en`, `status` (`open|closed`), `is_featured`, `amount` decimal(12,2), `issued_on` date, timestamps, `deleted_at`.
- Produces abstract `App\Livewire\DataTable extends Component` with:
  - URL state: `$search` (`q`), `$filters` (`f`, array), `$dateFrom` (`from`), `$dateTo` (`to`), `$sortBy` (`sort`), `$sortDir` (`dir`), `$perPage` (`per`, default 25), `$trashed` (`trash`, default false)
  - to implement: `tableKey(): string`, `query(): Builder`, `columns(): list<Column>`
  - overridable: `filterDefinitions(): list<Filter>` (default `[]`), `searchColumns(): list<string>` (supports `relation.column`), `dateColumn(): ?string` (default `created_at`), `defaultSort(): array{string,string}` (default `['created_at','desc']`), `permissionPrefix(): ?string`
  - public actions: `sort(string $key)`, `clearFilters()`, `removeFilter(string $key)`, `setDatePreset(string $preset)` with `today|this_week|this_month|last_month|this_year`
  - protected `filteredQuery(): Builder` — search, filters, date range and trashed applied, no ordering or paging
  - computed `rows(): LengthAwarePaginator`, `totals(): array<string,string>`
- `perPage` accepts `15, 25, 50, 100`; anything else becomes 25. Search is debounced 300ms in the view.
- `Column::make(string $key, string $labelKey)` with fluent `sortable(?string $dbColumn = null)`, `format(Closure $fn)`, `view(string $blade)`, `summable()`, `onCard()`, `alignEnd()`, `hiddenByDefault()`, `exportAs(Closure $fn)`.
- `SelectFilter::make(string $key, string $labelKey)->options(array|Closure)->column(string)`; every filter supports `->query(Closure(Builder $q, mixed $value))`. `NumberRangeFilter` value shape `['min' => ?, 'max' => ?]`.
- View: toolbar, collapsible filter row, active-filter chips each with a remove button plus "clear filters", table on `md` and up, cards built from `onCard()` columns below `md`, totals row for `summable()` columns, `<x-empty-state>` when there are no rows.

- [ ] **Step 1: Write the failing tests** (60 fixture rows unless stated)

```php
it('searches Arabic text', function () { /* row name_ar 'عزل الأسطح' → set('search','الأسطح') sees it only */ });
it('treats % and _ literally', function () { /* rows '50% خصم' and '500 خصم' → search '50%' returns the first only; search '_' returns none */ });
it('filters by select, multi-select and boolean', function () { /* status=closed; status in [open,closed]; is_featured=1 */ });
it('filters by amount range inclusive', function () { /* min 100 max 200 includes 100.00 and 200.00 */ });
it('includes the whole end day in the date range', function () { /* issued_on 2026-11-30, from 2026-11-01 to 2026-11-30 → included */ });
it('sets the range from a preset', function () { Carbon::setTestNow('2026-11-12'); /* setDatePreset('this_month') → dateFrom 2026-11-01, dateTo 2026-11-30 */ });
it('toggles sort direction on the same column', function () { /* sort('amount') asc, again desc */ });
it('falls back to 25 for an unknown page size', function () { /* set perPage 999 → 25 */ });
it('totals the whole filtered result, not the page', function () { /* 60 rows amount 10, status filter matching 40 → totals['amount'] === '400.00' */ });
it('resets to page 1 when the search changes', function () {});
it('keeps state in the query string', function () { /* Livewire::withQueryParams(['q' => 'x']) → search === 'x' */ });
```

- [ ] **Step 2: Run** `php artisan test --filter=CoreTest` — expected: FAIL.
- [ ] **Step 3: Implement `Column` and the filter classes.**
- [ ] **Step 4: Implement `DataTable`.** Escape search terms with `addcslashes($term, '%_\\')` and bind them to `LIKE ? ESCAPE '\\'`.
- [ ] **Step 5: Implement the shared view and partials.**
- [ ] **Step 6: Run** the filter — expected: PASS.
- [ ] **Step 7: Commit** `stage-0: data table core`.

---

### Task 7: DataTable actions — selection, delete, trash, export, preferences, saved filters

**Files:**
- Create: `app/Contracts/GuardsDeletion.php`, `app/Actions/DeleteRecords.php`, `app/Tables/DeleteResult.php`, `app/Services/TableExporter.php`, `app/Support/TrashRegistry.php`, `app/Models/{TablePreference,SavedFilter}.php` with migrations, `resources/views/livewire/data-table/{bulk-bar,columns-menu,saved-filters}.blade.php`
- Modify: `app/Livewire/DataTable.php`, `resources/views/livewire/data-table.blade.php`, `lang/{ar,en}/table.php`
- Test: `tests/Feature/DataTable/ActionsTest.php`

**Interfaces:**
- Produces `GuardsDeletion::deletionBlockedReason(User $by): ?string` (a translated sentence, or null when deletable).
- Produces `DeleteRecords::handle(Builder $query, User $by): DeleteResult`; `DeleteResult` has `int $deleted`, `int $skipped`, `?string $firstReason`. It walks the query with `chunkById(200)`.
- Produces `TableExporter::download(string $fileName, Builder $query, array $columns): StreamedResponse` — XLSX through `SimpleExcelWriter::streamDownload`, rows read with `lazyById(500)`, header row from column labels in the current locale.
- Produces `TrashRegistry::register(string $modelClass): void`, `::models(): list<string>`.
- Adds to `DataTable`: `$selected` (list of ids), `deleteOne(int $id)`, `deleteSelected()`, `deleteAll()`, `restore(int $id)`, `restoreSelected()`, `forceDelete(int $id)`, `export()`, `exportSelected()`, `toggleColumn(string $key)`, `saveFilter(string $name)`, `applySavedFilter(int $id)`, `deleteSavedFilter(int $id)`, and `protected bool $deletable = true`.
- Authorization: `"{prefix}.delete"`, `.restore`, `.force-delete`, `.export`. A null prefix or `$deletable = false` disables delete, restore and force-delete entirely.
- Selected ids are always intersected with `filteredQuery()` before acting.
- Feedback: `toast` event with `table.deleted` (`:count`) and, when rows were skipped, `table.skipped` (`:count`, `:reason`).
- Confirmation in the view: single delete → `confirmAction`; "delete all" and permanent delete → `confirmAction` with `requireText` set to the row count, which the user must type.
- `table_preferences`: `user_id`, `table_key`, `hidden_columns` json, `per_page`; unique (`user_id`, `table_key`). `saved_filters`: `user_id`, `table_key`, `name`, `state` json.

- [ ] **Step 1: Write the failing tests**

```php
it('delete all removes every filtered row across pages and nothing else', function () { /* 60 rows, 30 status=closed, perPage 15, filter closed → deleteAll → 30 trashed, 30 open untouched */ });
it('skips guarded rows and reports them', function () { /* fixture rows with is_featured block deletion → toast table.skipped with count */ });
it('ignores selected ids outside the filtered result', function () { /* selected contains an open row id while filter=closed → not deleted */ });
it('forbids delete without the permission', function () { /* user lacking widgets.delete → deleteOne forbidden, row intact */ });
it('lists only trashed rows on the trash tab and restores one', function () {});
it('needs force-delete permission for permanent delete', function () {});
it('does nothing destructive when deletable is false', function () {});
it('exports the filtered rows with visible columns only', function () { /* hide amount, filter closed → file has header without amount and 30 data rows (read back with SimpleExcelReader) */ });
it('stores hidden columns per user and table', function () {});
it('restores a saved filter', function () { /* search, filters, dates and sort come back after applySavedFilter */ });
it('cannot apply or delete a saved filter of another user', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter=ActionsTest` — expected: FAIL.
- [ ] **Step 3: Implement contract, action, `DeleteResult`, exporter, registry, models and migrations.**
- [ ] **Step 4: Extend `DataTable` and its view** (row checkboxes, select page, bulk bar, columns menu, saved-filters menu, trash tab).
- [ ] **Step 5: Run** the filter — expected: PASS.
- [ ] **Step 6: Commit** `stage-0: data table actions`.

---

### Task 8: Users and roles screens

**Files:**
- Create: `app/Http/Controllers/{UserController,RoleController}.php`, `app/Http/Requests/{StoreUserRequest,UpdateUserRequest,ResetUserPasswordRequest,StoreRoleRequest,UpdateRoleRequest}.php`, `app/Policies/{UserPolicy,RolePolicy}.php`, `app/Livewire/Tables/{UsersTable,RolesTable}.php`, `resources/views/{users,roles}/{index,form}.blade.php`, `lang/{ar,en}/users.php`
- Modify: `app/Models/User.php` (implements `GuardsDeletion`), a `Role` model extending spatie's (implements `GuardsDeletion`), `config/permission.php`, `routes/web.php`, `app/Support/Menu.php`
- Test: `tests/Feature/UsersTest.php`, `tests/Feature/RolesTest.php`

**Interfaces:**
- Routes: resource `users` and `roles` without `show` and `destroy`; `users.toggle-active` (PATCH), `users.reset-password` (PUT).
- `UsersTable`: search `name`, `email`, `phone`; filters role, `is_active`, created date; columns name, email, phone, roles, status, last login, created.
- Deletion guards: a user cannot delete themself; the last active `super-admin` cannot be deleted or deactivated; a role with `is_system` or with users cannot be deleted.
- Role form: `label_ar` (required), `label_en`, `scope` (`all|assigned`), and a permission matrix from `PermissionRegistry::modules()` — one row per module, one column per action, a select-all per row.
- Passwords: minimum 8 characters.

- [ ] **Step 1: Write the failing tests**

```php
it('returns 403 on users pages without users.view', function () {});
it('creates a user with roles', function () {});
it('rejects a duplicate email', function () {});
it('never deletes the acting user', function () { /* deleteAll on UsersTable leaves the actor */ });
it('protects the last super-admin from delete and deactivation', function () {});
it('lets an admin reset the password of another user', function () {});
it('saves the permission matrix', function () { /* role gets exactly the ticked permissions */ });
it('refuses to delete a system role or a role in use', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter="UsersTest|RolesTest"` — expected: FAIL.
- [ ] **Step 3: Implement models, policies, requests, controllers, tables, views, menu entries.** Register `User` in `TrashRegistry`.
- [ ] **Step 4: Run** the filter — expected: PASS.
- [ ] **Step 5: Commit** `stage-0: users and roles screens`.

---

### Task 9: Lookups (managed lists)

**Files:**
- Create: `app/Enums/LookupType.php`, `app/Models/Lookup.php`, `app/Models/Concerns/HasLocalizedName.php`, `app/Support/LookupUsage.php`, lookups migration, `database/seeders/LookupSeeder.php`, `app/Http/Controllers/LookupController.php`, `app/Http/Requests/SaveLookupRequest.php`, `app/Policies/LookupPolicy.php`, `app/Livewire/Tables/LookupsTable.php`, `resources/views/lookups/{index,form-modal}.blade.php`
- Modify: `routes/web.php`, `app/Support/Menu.php`, `lang/{ar,en}/settings.php`
- Test: `tests/Feature/LookupsTest.php`

**Interfaces:**
- `LookupType` (string enum): `Service='service'`, `LeadStatus='lead_status'`, `LeadSource='lead_source'`, `RejectionReason='rejection_reason'`, `Unit='unit'`, `ExpenseCategory='expense_category'`, `AttachmentCategory='attachment_category'`, `Emirate='emirate'`, `Area='area'`, `PaymentMethod='payment_method'`, `WorkerTrade='worker_trade'`, `EquipmentType='equipment_type'`. Methods: `label(): string`, `parentType(): ?self` (`Area` → `Emirate`), `hasColor(): bool` (`LeadStatus` only).
- `lookups` columns: `type` (indexed), `parent_id` (nullable FK), `name_ar`, `name_en` (nullable), `code` (nullable), `color` (nullable), `is_active` (default true), `is_system` (default false), `sort_order`, timestamps, `deleted_at`; index (`type`, `is_active`, `sort_order`).
- `HasLocalizedName`: accessor `name` returns `name_{locale}` or `name_ar` when empty.
- `Lookup::options(LookupType $type, ?int $parentId = null): Collection` — active rows ordered by `sort_order`, cached under `lookups.{type}`, cache cleared on save, delete and restore. Scopes `ofType()`, `active()`.
- `LookupUsage::register(LookupType $type, Closure(Lookup): bool $inUse): void`; `Lookup` implements `GuardsDeletion` and blocks when `is_system`, when it has children, or when any registered check returns true.
- Routes: `lookups.index` (GET `/settings/lists/{type}`), `lookups.store` (POST), `lookups.update` (PUT), `lookups.reorder` (POST, body `ids[]`). Store and update are AJAX from a modal and return JSON; validation errors are 422 with field messages.
- Seed data: services (9: villas and residential, commercial and government, interior and exterior finishing, epoxy flooring, floor and roof insulation, acoustic insulation, film studio fit-out, infrastructure concrete and renovation, swimming pools); lead statuses with codes `new, contacted, visit, quoted, won, lost` (system); lead sources (`website` system, WhatsApp, phone, walk-in, referral, social media); units (m², linear m, m³, piece, lump sum, day, ton); payment methods (cash, bank transfer, cheque); the 7 emirates; attachment categories (drawings, contracts, photos, videos, documents); expense categories (materials, labour, transport, equipment, subcontractor, administrative, other). Every row has Arabic and English names.

- [ ] **Step 1: Write the failing tests**

```php
it('falls back to Arabic when the English name is empty', function () { app()->setLocale('en'); expect(Lookup::factory()->make(['name_ar' => 'عزل', 'name_en' => null])->name)->toBe('عزل'); });
it('caches options and refreshes them on save', function () {});
it('leaves inactive rows out of options', function () {});
it('requires an emirate parent for an area', function () {});
it('refuses to delete a system row, a row with children, or a row in use', function () {});
it('returns 422 JSON with field errors from the modal', function () { /* postJson without name_ar → 422, errors.name_ar */ });
it('persists a new order', function () { /* reorder ids [3,1,2] → sort_order 1,2,3 in that order */ });
it('seeds six lead statuses with codes', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter=LookupsTest` — expected: FAIL.
- [ ] **Step 3: Implement enum, model, trait, usage registry, migration, seeder.**
- [ ] **Step 4: Implement controller, request, policy, table, views.** One screen serves every type; SortableJS drag handle calls `lookups.reorder`. Register `Lookup` in `TrashRegistry`.
- [ ] **Step 5: Run** the filter — expected: PASS.
- [ ] **Step 6: Commit** `stage-0: managed lists`.

---

### Task 10: Settings screens

**Files:**
- Create: `app/Http/Controllers/SettingsController.php`, `app/Http/Requests/Settings/{Company,Appearance,Finance,Upload,System}SettingsRequest.php`, `resources/views/settings/{index,company,appearance,finance,uploads,system}.blade.php`
- Modify: `routes/web.php`, `app/Support/Menu.php`, `lang/{ar,en}/settings.php`
- Test: `tests/Feature/SettingsScreensTest.php`

**Interfaces:**
- Routes: `settings.index` (GET `/settings`), `settings.edit` (GET `/settings/{group}`), `settings.update` (PUT `/settings/{group}`), `group` in `company|appearance|finance|uploads|system`. All require `settings.manage`.
- Branding images (logo, login logo, stamp, signature): normal file inputs, `image`, max 2 MB, stored on the `public` disk under `branding/`; the old file is deleted on replace.
- Validation: colours `^#[0-9A-Fa-f]{6}$`; `primary_font` in `cairo,tajawal`; `heading_font` in `same,other`; `decimals` 0–3; `vat_rate` 0–100; day counts 1–365; upload sizes 1–1024 MB; `chunk_size_mb` 1–10; `trash_retention_days` nullable 1–3650; `session_idle_minutes` 5–1440.
- `settings.index` lists the groups of §7 that exist so far, each linking to its screen (lists link to `lookups.index`).

- [ ] **Step 1: Write the failing tests**

```php
it('returns 403 without settings.manage', function () {});
it('saves company data and it is readable on the next request', function () {});
it('rejects an invalid colour', function () { /* color_navy 'blue' → error */ });
it('accepts only cairo or tajawal as primary font', function () {});
it('rejects vat above 100 and decimals above 3', function () {});
it('replaces the logo and deletes the old file', function () { /* Storage::fake('public') */ });
it('applies a changed primary colour to the page', function () { /* color_navy #112233 → response contains --c-navy:#112233 */ });
```

- [ ] **Step 2: Run** `php artisan test --filter=SettingsScreensTest` — expected: FAIL.
- [ ] **Step 3: Implement requests, controller, views.** The controller maps `group` to its settings class and request.
- [ ] **Step 4: Run** the filter — expected: PASS.
- [ ] **Step 5: Commit** `stage-0: settings screens`.

---

### Task 11: Document numbering

**Files:**
- Create: `app/Enums/DocumentType.php`, `app/Models/DocumentSequence.php` with migration, `database/seeders/DocumentSequenceSeeder.php`, `app/Services/NumberGenerator.php`, `app/Http/Controllers/NumberingController.php`, `app/Http/Requests/UpdateSequenceRequest.php`, `resources/views/settings/numbering.blade.php`
- Modify: `routes/web.php`, `resources/views/settings/index.blade.php`
- Test: `tests/Feature/NumberGeneratorTest.php`

**Interfaces:**
- `DocumentType` (value / default prefix): `Lead` lead / LD, `SiteVisit` site_visit / SV, `Quotation` quotation / QT, `Project` project / PRJ, `Variation` variation / VO, `Invoice` invoice / INV, `Receipt` receipt / RV, `Expense` expense / EXP, `Claim` claim / PC, `Purchase` purchase / PB, `PaymentVoucher` payment_voucher / PV, `Maintenance` maintenance / MR. Method `defaultPrefix(): string`.
- `document_sequences`: `type` (unique), `prefix`, `padding` (default 4), `next_number` (default 1), `include_year` (default true), `reset_yearly` (default true), `year` (nullable int).
- `NumberGenerator::next(DocumentType $type, ?CarbonInterface $date = null): string` — runs in `DB::transaction` with `lockForUpdate()`; format `{prefix}-{YYYY}-{padded}` or `{prefix}-{padded}` when `include_year` is false.
- `NumberGenerator::preview(DocumentType $type): string` — the next number without consuming it.
- Screen `settings.numbering` (GET/PUT `/settings/numbering`): per type edit `prefix` (1–10 chars, letters, digits, dash), `padding` (3–8), `next_number` (may only stay or increase), `include_year`, `reset_yearly`; shows the preview.

- [ ] **Step 1: Write the failing tests**

```php
beforeEach(fn () => Carbon::setTestNow('2026-11-12'));
it('issues sequential numbers', function () { $g = app(NumberGenerator::class); expect($g->next(DocumentType::Quotation))->toBe('QT-2026-0001')->and($g->next(DocumentType::Quotation))->toBe('QT-2026-0002'); });
it('restarts on a new year', function () { /* after 0002, setTestNow 2027-01-02 → 'QT-2027-0001' */ });
it('keeps counting when yearly reset is off', function () { /* reset_yearly=false → 'QT-2027-0003' */ });
it('omits the year when configured', function () { /* include_year=false → 'QT-0001' */ });
it('honours padding', function () { /* padding 6 → 'INV-2026-000001' */ });
it('issues 50 unique numbers in a row', function () {});
it('does not consume a number when the outer transaction rolls back', function () { /* DB::transaction throws after next() → following next() returns the same number */ });
it('preview does not consume', function () {});
it('rejects lowering next_number', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter=NumberGeneratorTest` — expected: FAIL.
- [ ] **Step 3: Implement enum, model, migration, seeder (one row per type), generator.**
- [ ] **Step 4: Implement the screen.**
- [ ] **Step 5: Run** the filter — expected: PASS.
- [ ] **Step 6: Commit** `stage-0: document numbering`.

---

### Task 12: Activity log

**Files:**
- Create: `app/Models/Concerns/LogsModelActivity.php`, `app/Services/ActivityLogger.php`, `app/Http/Controllers/ActivityController.php`, `app/Livewire/Tables/ActivityTable.php`, `resources/views/activity/index.blade.php`, `resources/views/components/activity-tab.blade.php`, `resources/views/livewire/data-table/activity-diff.blade.php`
- Modify: `app/Models/{User,Lookup,Role,DocumentSequence}.php`, `LoginController`, `SettingsController`, `app/Livewire/DataTable.php` (log exports), `routes/web.php`, `app/Support/Menu.php`
- Test: `tests/Feature/ActivityLogTest.php`

**Interfaces:**
- `LogsModelActivity`: logs fillable attributes, dirty only, no empty logs; log name from `activityModule(): string` (default: table name); `protected array $activityHidden = []` is never logged.
- `ActivityLogger::record(string $event, ?Model $subject = null, array $properties = [], ?string $module = null): void` for events that are not model changes: `login`, `logout`, `exported`, `printed`, `status_changed`, `settings_updated`.
- `ActivityTable`: `$deletable = false`; optional mount params `subjectType`, `subjectId`; filters causer, module, event, date range; a row expands to show old and new values side by side.
- `<x-activity-tab :subject="$model" />` renders `ActivityTable` scoped to that record.
- Route `activity.index` (GET `/activity`), permission `activity.view`.

- [ ] **Step 1: Write the failing tests**

```php
it('logs only the changed attributes with old and new values', function () { /* rename user → properties.old.name, properties.attributes.name, no email key */ });
it('never logs the password', function () { /* change password → no activity property contains 'password' */ });
it('logs login and logout', function () {});
it('logs a settings update with the changed keys', function () {});
it('logs an export with the table key', function () {});
it('returns 403 on the activity page without activity.view', function () {});
it('offers no way to delete activity', function () { /* ActivityTable deleteOne is forbidden and the row remains */ });
it('scopes the tab to one record', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter=ActivityLogTest` — expected: FAIL.
- [ ] **Step 3: Implement trait, logger, table, controller, views; apply the trait and the explicit log calls.**
- [ ] **Step 4: Run** the filter — expected: PASS.
- [ ] **Step 5: Commit** `stage-0: activity log`.

---

### Task 13: Upload component and attachments

**Files:**
- Create: `app/Enums/UploadKind.php`, `app/Services/Uploads/{TemporaryUpload,ChunkReceiver,UploadValidator}.php`, `app/Exceptions/InvalidUploadException.php`, `app/Actions/AttachUploads.php`, `app/Models/Concerns/HasAttachments.php`, `app/Http/Controllers/{UploadController,MediaController}.php`, `resources/js/uploader.js`, `resources/views/components/{uploader,attachments}.blade.php`
- Modify: `vite.config.js` (add `resources/js/uploader.js`), `package.json`, `routes/web.php`, `lang/{ar,en}/{app,js}.php`, `tests/Fixtures/Widget.php` (uses `HasAttachments`, has a policy)
- Test: `tests/Feature/Uploads/ChunkUploadTest.php`, `tests/Feature/Uploads/AttachTest.php`, `tests/Feature/Uploads/MediaAccessTest.php`

**Interfaces:**
- npm: `filepond@^4`, `filepond-plugin-file-validate-type`, `filepond-plugin-file-validate-size`, `filepond-plugin-image-preview`, `filepond-plugin-image-exif-orientation`, `filepond-plugin-image-resize`, `filepond-plugin-image-transform`.
- `UploadKind`: `Image`, `Video`, `File`.
- Temp storage: `private` disk, `tmp-uploads/{uuid}/data` plus `meta.json` (`name`, `size`, `offset`, `user_id`) and optional `poster.jpg`.
- Routes (auth), following FilePond's chunk protocol:
  - `POST /uploads` — with header `Upload-Length`: creates a transfer and returns the id as plain text; with a `filepond` file field: stores a small file whole and returns the id
  - `PATCH /uploads/{id}` — headers `Upload-Offset`, `Upload-Length`, `Upload-Name`; body is the chunk; 409 when the offset differs from the stored offset
  - `HEAD /uploads/{id}` — returns header `Upload-Offset`
  - `DELETE /uploads/{id}` — removes the transfer
  - `POST /uploads/{id}/poster` — JPEG poster for a video, max 1 MB
  - every route returns 403 when the transfer belongs to another user
- `UploadValidator::kind(string $path, string $originalName): UploadKind` — decides by real MIME (`finfo`) and extension against `UploadSettings`, enforces the size limit of that kind, throws `InvalidUploadException` otherwise.
- `AttachUploads::handle(HasMedia $model, array $uploadIds, User $by, array $meta = [], string $collection = 'attachments'): Collection` — validates ownership and kind, adds to the media library, sets custom properties `kind`, `title`, `category_id`, `uploaded_by`, stores a poster as media in collection `posters` with custom property `media_id`, deletes the temp folder. On an invalid file it deletes the temp folder and rethrows.
- `HasAttachments`: collection `attachments` on the `private` disk; conversion `thumb` (WebP, width 400, images only, `nonQueued`).
- `MediaController`: `media.show` (GET `/media/{media}`), `media.thumb` (GET `/media/{media}/thumb`), `media.destroy` (DELETE). Show and thumb authorize `view` on the owning model and send `Cache-Control: private, max-age=604800`; destroy authorizes `update`.
- `<x-uploader name="uploads" :multiple="true" accept="image,video,file" />` — emits hidden `uploads[]` inputs with transfer ids. `uploader.js` is loaded only on pages that use the component (`@pushOnce('scripts')`), and configures: chunked uploads with `chunk_size_mb`, retry delays 500/1000/3000 ms, client-side image resize to `image_max_dimension` at quality 0.82, paste and drag-and-drop, camera capture on mobile, a canvas-captured poster for videos, labels from `window.I18N`.
- `<x-attachments :model="$model" />` — grid with image lightbox, video player, PDF preview in a modal; title search and filters by kind and category run in the browser; each item shows title, category, uploader and date.

- [ ] **Step 1: Write the failing tests**

```php
it('assembles a file sent in two chunks', function () { /* 5 MB random bytes, chunks of 3 MB and 2 MB → sha1 of temp data equals sha1 of the source */ });
it('resumes from the stored offset', function () { /* after chunk 1, HEAD returns Upload-Offset 3145728; then chunk 2 completes; sha1 equal */ });
it('rejects a chunk at the wrong offset with 409', function () {});
it('forbids another user from touching a transfer', function () { /* PATCH, HEAD, DELETE → 403 */ });
it('rejects a fake image and removes its temp data', function () { /* 'shell.jpg' containing '<?php echo 1;' → InvalidUploadException / 422, temp folder gone, no media row */ });
it('rejects a file larger than the limit of its kind', function () { /* image_max_mb=1, 2 MB png → rejected */ });
it('keeps an Arabic file name', function () { /* 'مخطط الدور الأول.pdf' → media name 'مخطط الدور الأول' */ });
it('creates a webp thumbnail for an image', function () {});
it('links a video poster to its media', function () {});
it('cannot attach a transfer of another user', function () {});
it('serves media only to users who can view the owner', function () { /* 200 with permission, 403 without */ });
it('reverts a transfer', function () { /* DELETE → temp folder gone */ });
```

- [ ] **Step 2: Run** `php artisan test --filter="ChunkUploadTest|AttachTest|MediaAccessTest"` — expected: FAIL.
- [ ] **Step 3: Implement enum, temp upload services, validator, exception.**
- [ ] **Step 4: Implement `UploadController`, `AttachUploads`, `HasAttachments`, `MediaController`.**
- [ ] **Step 5: Implement the two Blade components and `uploader.js`.**
- [ ] **Step 6: Run** the filter — expected: PASS. Then `npm run build`.
- [ ] **Step 7: Try it by hand** on a temporary test page: upload a 150 MB video with the network throttled, cut the connection mid-upload, reconnect, and confirm it completes.
- [ ] **Step 8: Commit** `stage-0: upload component and attachments`.

---

### Task 14: Notifications and due tasks

**Files:**
- Create: `app/Enums/NotificationType.php`, `app/Settings/NotificationSettings.php` with settings migration, `app/Notifications/SystemNotification.php`, `app/Models/NotificationDispatch.php` with migration, `app/Services/{Notifier,DueTasks}.php`, `app/Http/Middleware/RunDueTasks.php`, `app/Console/Commands/RunDueTasksCommand.php`, `app/Http/Controllers/{NotificationController,NotificationSettingsController}.php`, `app/Livewire/Tables/NotificationsTable.php`, `resources/views/notifications/{index,latest}.blade.php`, `resources/views/settings/notifications.blade.php`, `resources/js/ui/notifications.js`, `lang/{ar,en}/notifications.php`
- Modify: notifications table migration (`php artisan make:notifications-table`), `resources/views/partials/topbar.blade.php`, `routes/web.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/NotifierTest.php`, `tests/Feature/DueTasksTest.php`, `tests/Feature/NotificationScreensTest.php`

**Interfaces:**
- `NotificationType` (string enum) / default days / default roles: `LeadFromWebsite` – / sales; `Assigned` – / direct user; `VisitToday` – / direct user; `QuotationNoResponse` 3 / direct user; `QuotationExpiring` 3 / direct user; `InstallmentDue` 3 / accountant, management; `InstallmentOverdue` – / accountant, management; `InvoiceOverdue` – / accountant, management; `StageLate` – / management; `ProjectEnding` 7 / direct user; `WarrantyEnding` 30 / management; `BackupFailed` – / super-admin. Methods `usesDays(): bool`, `defaultDays(): ?int`, `defaultRoles(): list<string>`.
- `NotificationSettings` (group `notifications`): `rules` array keyed by type value; `rule(NotificationType $type): array{enabled: bool, days: ?int, roles: list<string>}` merges stored values over defaults.
- `Notifier::toRoles(NotificationType $type, Model $subject, array $params, string $url, ?string $dedupeKey = null): int` and `Notifier::toUser(User $user, NotificationType $type, Model $subject, array $params, string $url, ?string $dedupeKey = null): bool`. Both skip disabled types and inactive users. With a dedupe key, a second call for the same (type, subject, key) sends nothing; the record is in `notification_dispatches` with a unique index on (`type`, `subject_type`, `subject_id`, `dedupe_key`).
- Notification data: `type`, `title_key`, `params`, `url`, `subject_type`, `subject_id`. Titles are translated at display time from `notifications.{type}`.
- `DueTasks::register(string $name, int $everyMinutes, Closure $job): void` and `DueTasks::runDue(): void` — per task: skip when the last run is newer than the interval, take `Cache::lock("due:{$name}", 300)`, run, store the run time. An exception in one task is logged and does not stop the others or the request.
- `RunDueTasks` middleware calls `runDue()` in `terminate()` for authenticated web requests. `php artisan app:due-tasks` does the same for hosts that have cron.
- Registered now: `purge-trash` every 1440 minutes (force-deletes rows in `TrashRegistry::models()` trashed longer than `trash_retention_days`; does nothing when null) and `purge-temp-uploads` every 60 minutes (temp folders older than 24 hours).
- Routes: `notifications.index` (GET), `notifications.latest` (GET, HTML partial with the 10 newest), `notifications.read` (POST `/notifications/{id}/read`, then redirects to the notification's `url`), `notifications.read-all` (POST), `settings.notifications` (GET/PUT, `settings.manage`).
- Topbar: unread count rendered on the server; the dropdown fetches `notifications.latest` when opened.

- [ ] **Step 1: Write the failing tests**

```php
it('notifies active users of the configured roles only', function () { /* 2 sales (1 inactive), 1 engineer → toRoles(LeadFromWebsite) returns 1 */ });
it('sends nothing for a disabled type', function () {});
it('sends once per dedupe key', function () { /* two calls with key 'due-2026-11-12' → 1 notification */ });
it('runs a task once inside its interval and again after it', function () { /* register every 60; runDue twice → 1 call; travel 61 minutes; runDue → 2 calls */ });
it('keeps going when a task throws', function () { /* first task throws, second still runs, no exception escapes */ });
it('purges trash older than the retention and nothing when retention is null', function () {});
it('shows the unread count and clears it on read-all', function () {});
it('marks one as read and redirects to its url', function () {});
it('returns 404 for a notification of another user', function () {});
it('saves notification rules', function () { /* disable StageLate, QuotationExpiring days 5 → rule() reflects both */ });
```

- [ ] **Step 2: Run** `php artisan test --filter="NotifierTest|DueTasksTest|NotificationScreensTest"` — expected: FAIL.
- [ ] **Step 3: Implement enum, settings, notification class, dispatch model, `Notifier`.**
- [ ] **Step 4: Implement `DueTasks`, middleware, command, the two registered tasks.**
- [ ] **Step 5: Implement controllers, table (filters: type, read state, date), views, topbar bell, settings screen.**
- [ ] **Step 6: Run** the filter — expected: PASS.
- [ ] **Step 7: Commit** `stage-0: notifications and due tasks`.

---

### Task 15: Global search

**Files:**
- Create: `app/Search/{SearchProvider,SearchResult,SearchRegistry,UserSearchProvider}.php`, `app/Http/Controllers/SearchController.php`, `resources/js/ui/search.js`, `resources/views/partials/search-modal.blade.php`
- Modify: `resources/views/partials/topbar.blade.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`, `lang/{ar,en}/app.php`
- Test: `tests/Feature/GlobalSearchTest.php`

**Interfaces:**
- `SearchProvider`: `key(): string`, `labelKey(): string`, `search(string $term, User $user, int $limit = 5): Collection<int, SearchResult>`.
- `SearchResult`: readonly `string $title`, `?string $subtitle`, `string $url`.
- `SearchRegistry` (singleton): `register(SearchProvider $provider): void`, `search(string $term, User $user): array` — returns `[['key' =>, 'label' =>, 'results' => [...]], ...]`, empty groups removed, empty array when the trimmed term is shorter than 2 characters.
- `UserSearchProvider`: name, email, phone; returns nothing without `users.view`; LIKE wildcards escaped as in Task 6.
- Route `search` (GET `/search?q=`) returns that array as JSON.
- `search.js`: opens on `Ctrl+K` or the topbar field, 250 ms debounce, aborts the previous request, arrow keys and Enter to navigate, Escape to close.

- [ ] **Step 1: Write the failing tests**

```php
it('returns nothing for a one-character term', fn () => actingAs(User::factory()->create())->getJson('/search?q=a')->assertExactJson([]));
it('groups results by provider', function () { /* user 'سعيد' → first group key 'users', results[0].title 'سعيد', url route users.edit */ });
it('hides a group the user may not view', function () {});
it('matches % literally', function () {});
it('limits each group to 5', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter=GlobalSearchTest` — expected: FAIL.
- [ ] **Step 3: Implement interfaces, registry, provider, controller, modal, script.**
- [ ] **Step 4: Run** the filter — expected: PASS.
- [ ] **Step 5: Commit** `stage-0: global search`.

---

### Task 16: Translation editor

**Files:**
- Create: `app/Support/TranslationCatalog.php`, `app/Http/Controllers/TranslationController.php`, `app/Http/Requests/SaveTranslationRequest.php`, `resources/views/settings/translations.blade.php`
- Modify: `config/translation-loader.php`, `lang/{ar,en}/{validation,pagination,passwords}.php` (copy from `C:\laragon\www\class-skilled\lang\`), `routes/web.php`, `resources/views/settings/index.blade.php`
- Test: `tests/Feature/TranslationsTest.php`

**Interfaces:**
- `TranslationCatalog::all(): Collection` of `['group', 'key', 'ar', 'en', 'overridden']` — every key of the app's own lang files (`app`, `auth`, `table`, `users`, `settings`, `notifications`, `js`), flattened with dot keys, with database overrides merged in.
- Routes (`translations.manage`): `translations.index` (GET `/settings/translations`), `translations.update` (PUT, body `group`, `key`, `ar`, `en`; saves a `LanguageLine`), `translations.reset` (DELETE, removes the override).
- The page is plain Blade with one row per key, in-browser search and a group filter, an "overridden only" toggle, and a modal editor that saves by AJAX. It is not a `DataTable`.

- [ ] **Step 1: Write the failing tests**

```php
it('overrides a label from the database', function () { /* put app.save ar='حفظ التغييرات' → __('app.save') returns it */ });
it('restores the file value on reset', function () {});
it('lists every key of the app groups in both languages', function () { /* catalog contains group app key save with ar and en non-empty */ });
it('has an English and an Arabic value for every key', function () { /* no key present in one locale and missing in the other */ });
it('returns 403 without translations.manage', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter=TranslationsTest` — expected: FAIL.
- [ ] **Step 3: Implement catalog, controller, request, view; enable the database loader after the file loader.**
- [ ] **Step 4: Run** the filter — expected: PASS.
- [ ] **Step 5: Commit** `stage-0: translation editor`.

---

### Task 17: Home, reference screens and stage gate

**Files:**
- Create: `app/Http/Controllers/DesignController.php`, `resources/views/design/{index,record}.blade.php`, `resources/views/components/{record-header,tabs,sticky-actions}.blade.php`, `database/seeders/DemoSeeder.php`, `docs/screens/stage-0/` (screenshots), `docs/plans/PROGRESS.md`
- Modify: `resources/views/home.blade.php`, `HomeController`, `routes/web.php`, `resources/scss/_components.scss`, `resources/scss/_mobile.scss`
- Test: `tests/Feature/HomeTest.php`, `tests/Feature/DesignScreensTest.php`

**Interfaces:**
- Home: the user's unread notifications and their own latest 10 activity entries. Figures arrive in Stage 3.
- `design.index` (GET `/design`, `settings.manage`): one page showing every shared component with realistic Arabic and English contracting data — buttons, all form controls inside `<x-field>`, status badges in each state colour, toasts and both confirmation types, a live `LookupsTable`, the uploader, the attachments grid, an empty state.
- `design.record` (GET `/design/record`): a sample record page — `<x-record-header>` (title, status, six summary figures), `<x-tabs>`, a long form with `<x-sticky-actions>`.
- `<x-record-header :title :status>` with `figures` and `actions` slots; `<x-tabs :tabs :active>`; `<x-sticky-actions>` (save bar fixed to the bottom of the viewport).
- `DemoSeeder` (local only): 12 users across the roles and 50,000 extra lookups of type `rejection_reason` for the speed check.

- [ ] **Step 1: Write the failing tests**

```php
it('shows my unread notifications and my activity on home', function () {});
it('renders the design pages in both languages without errors', function () { /* ar and en → 200 */ });
it('keeps design pages behind settings.manage', function () {});
```

- [ ] **Step 2: Run** `php artisan test --filter="HomeTest|DesignScreensTest"` — expected: FAIL.
- [ ] **Step 3: Implement home, the three components, the two design pages, `DemoSeeder`.**
- [ ] **Step 4: Run** the filter — expected: PASS.
- [ ] **Step 5: Visual pass.** For `/login`, `/`, `/users`, `/users/create`, `/settings/lists/service`, `/design`, `/design/record`: capture 390px and 1440px in Arabic and English into `docs/screens/stage-0/`. Check each against §4.2–§4.4: no horizontal scroll, tables become cards under 768px, logical alignment correct in both directions, no emoji, no gradients, headings 22px or less.
- [ ] **Step 6: Speed check.** Seed `DemoSeeder` on MySQL, open `/settings/lists/rejection_reason`, search and filter; with `DB::listen` logging, the table queries must total under 300 ms. Add indexes if not, and record the measured times in `PROGRESS.md`.
- [ ] **Step 7: Quality gates**

Run: `php artisan migrate:fresh --seed && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && php artisan test && npm run build`
Expected: all succeed, and `storage/logs/laravel.log` has no new errors.

- [ ] **Step 8: Write `docs/plans/PROGRESS.md`** — what Stage 0 delivered, decisions taken, known gaps, measured speed, and "next: Stage 1 plan".
- [ ] **Step 9: Commit** `stage-0: home, reference screens and stage gate`.
- [ ] **Step 10: Stop for approval.** Show the user the screenshots and the running `/design` pages. Stage 1 planning starts only after the user approves the look (§4.6); apply requested changes here first.
