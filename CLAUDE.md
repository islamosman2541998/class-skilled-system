# Class Skilled System — Project Rules

Business management system for Class Skilled General Contracting (Abu Dhabi).
Separate from the public website project (`C:\laragon\www\class-skilled`).

- Spec (Arabic, binding): `docs/specs/2026-11-12-class-skilled-system-design.md`
- Plans: `docs/plans/`. One stage plan at a time. Progress log: `docs/plans/PROGRESS.md`.
- Local URL `http://class-skilled-system.test`, MySQL database `class_skilled_system` (Laragon, root, no password).

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

