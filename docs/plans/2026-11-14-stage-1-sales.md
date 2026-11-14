# Stage 1 — Sales Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clients, requests (leads) with follow-ups, site visits, price items, quotations with revisions, PDF and WhatsApp, and the website request API, all on the Stage 0 foundation.

**Architecture:** Each module follows the Stage 0 pattern: Model + migration, Form Requests, thin controller, one `DataTable` subclass, Blade views, feature tests. Business rules live in `app/Actions` and `app/Services` (quotation totals, revisions, lead conversion, website intake). One `SalesServiceProvider` registers everything the module adds to the Stage 0 registries (permissions, menu, trash, search, lookup usage, due tasks). Record visibility for "assigned only" roles is one trait used by every query, page, media and search path.

**Tech Stack:** As Stage 0, plus `mpdf/mpdf ^8.2` (approved by the user on 2026-11-14). Laravel `Http` client for the "fetch requests" button.

**Spec:** `docs/specs/2026-11-12-class-skilled-system-design.md` (Arabic, binding). Sections: §8.1–§8.5, §5.4, §5.5, §5.6, §5.9, §5.10, §6, §7 (document and payment templates), §10.

## Global Constraints

- Everything in `CLAUDE.md` → Global Constraints applies unchanged (packages, shared hosting, Livewire only in tables, no hardcoded text, §4.2 text and visual rules, logical direction, western digits, `money()` / `fmt_date()`, `name_ar` + nullable `name_en`, soft deletes, every action authorized, Arabic default and fallback).
- Only new package: `mpdf/mpdf ^8.2`. No npm package.
- Money columns `decimal(14,2)`; quantities `decimal(12,3)`; percentages `decimal(5,2)`. All totals computed on the server.
- VAT rate is copied into each quotation when it is created and never re-read from settings for that quotation (§5.9).
- Document numbers come from `NumberGenerator` (`DocumentType::Lead`, `SiteVisit`, `Quotation`) inside the transaction that creates the record.
- Every new lang group is added to `TranslationCatalog::GROUPS`.
- Commit at the end of every task with the message shown. Local commits only.
- The website project (`D:\laragon\www\class-skilled`) is not edited in this stage (user decision 2026-11-14). Task 10 writes instructions for it instead.

## Review Focus

Each line has a pinning test in the task named in brackets.

1. A user whose roles are all "assigned" scope cannot reach another user's lead, visit or quotation by URL, by `media.show`, by global search, by export, or by "delete all". [Tasks 2, 3, 4, 7]
2. An accepted quotation cannot be changed through any route: update, item edit, reject, a second accept; only copy and PDF work. [Task 8]
3. Totals posted by the browser are ignored; a percent discount over 100 or an amount discount over the subtotal is rejected; rounding follows the `decimals` setting; changing the VAT setting does not change an existing quotation. [Task 7]
4. The website API is idempotent: the same `external_id` sent twice (a retry after a timeout) returns the same lead and creates no duplicate, attachment or notification. [Task 10]
5. A phone written `0501234567`, `+971 50 123 4567` or `00971501234567` is detected as a duplicate of the same client. [Task 1]

---

### Task 1: Sales module skeleton and clients

**Files:**
- Create: `app/Providers/SalesServiceProvider.php` (add to `bootstrap/providers.php`), `app/Enums/ClientType.php`, `app/Models/{Client,ClientContact}.php` + migrations, `app/Support/Phone.php`, `app/Http/Controllers/{ClientController,ClientContactController}.php`, `app/Http/Requests/{SaveClientRequest,SaveClientContactRequest}.php`, `app/Livewire/Tables/ClientsTable.php`, `app/Search/ClientSearchProvider.php`, `resources/views/clients/{index,form,show,quick-add-modal}.blade.php`, `resources/views/clients/partials/*`, `lang/{ar,en}/clients.php`, `lang/{ar,en}/menu.php` (sales group keys if missing), `database/factories/ClientFactory.php`
- Modify: `app/Support/TranslationCatalog.php`, `app/Http/Controllers/MediaController.php`
- Test: `tests/Feature/Sales/ClientsTest.php`, `tests/Unit/PhoneTest.php`

**Interfaces:**
- Consumes: `DataTable`, `GuardsDeletion`, `HasAttachments`, `LogsModelActivity`, `HasLocalizedName`, `Lookup::options()`, `SearchRegistry`, `PermissionRegistry::register()`, `Menu::register()`, `TrashRegistry::register()`, `<x-record-header>`, `<x-tabs>`, `<x-attachments>`, `<x-activity-tab>`.
- Produces:
  - `SalesServiceProvider::boot()` — the only place this stage registers permissions, menu items (group `sales`), trash models, search providers, `LookupUsage` checks and `DueTasks`. Later tasks add lines here.
  - Permissions: `clients` → all `PermissionRegistry::ACTIONS`.
  - `ClientType` enum: `Individual='individual'`, `Company='company'`; `label()`.
  - `clients`: `type`, `name_ar`, `name_en` (nullable), `phone`, `phone_normalized` (indexed), `phone2` (nullable), `whatsapp` (nullable), `email` (nullable), `trn` (nullable, 15 digits), `emirate_id` / `area_id` (nullable FK lookups), `address` (nullable text), `source_id` (nullable FK lookup), `notes` (nullable text), `created_by` (FK users), timestamps, `deleted_at`. Indexes on `name_ar`, `email`, `trn`, `created_at`.
  - `client_contacts`: `client_id`, `name`, `position` (nullable), `phone` (nullable), `email` (nullable), timestamps.
  - `Phone::normalize(?string $phone): ?string` — digits only; `00971…` and `+971…` → `971…`; a local `05xxxxxxxx` → `9715xxxxxxxx`; empty → null.
  - `Client::scopeVisibleTo(Builder $q, User $user): Builder` (all rows for now; Task 2 narrows it), `Client::isVisibleTo(User $user): bool`, `Client::deletionBlockedReason()` returns `clients.has_records` when it has leads, visits or quotations (Tasks 2–7 extend the check through `Client::$linkedRelations`).
  - `MediaController::authorizeOwner` additionally aborts 404 when the owner has `isVisibleTo()` and it returns false.
  - Routes: resource `clients` (no destroy), `clients.contacts.store|update|destroy`, `clients.attachments.store`, `clients.duplicates` (GET `?phone=` → JSON list `[{id, name, url}]`), `clients.quick-store` (POST, JSON, used by the quick-add modal).
  - `<x-client-quick-add />` modal include: on success dispatches a browser event `client-created` with `{id, name}` so any client `<select data-select>` can add and pick it.
  - Show page tabs (keys): `overview`, `contacts`, `leads`, `visits`, `quotations`, `attachments`, `activity`. Tabs whose module does not exist yet are not rendered (`Route::has`).
  - Summary figures on the show page: number of quotations, total of accepted quotations, accepted count. (Projects, invoiced, collected arrive in Stages 2–3; so does the §8.1 filter "has an outstanding balance".)

- [ ] **Step 1: Write the failing tests**

```php
// tests/Unit/PhoneTest.php
it('normalizes UAE phone formats to one value', function (string $raw) {
    expect(Phone::normalize($raw))->toBe('971501234567');
})->with(['0501234567', '+971 50 123 4567', '00971501234567', '971-50-123-4567']);
it('returns null for an empty phone', fn () => expect(Phone::normalize('  '))->toBeNull());

// tests/Feature/Sales/ClientsTest.php
it('returns 403 on client pages without clients.view');
it('creates a company client with contacts and stores the normalized phone');           // phone_normalized === '971501234567'
it('reports a duplicate phone written differently', function () { /* client 0501234567 exists → getJson(clients.duplicates, phone '+971 50 123 4567') returns 1 item with its name */ });
it('excludes the client itself from duplicates when editing');                          // ?phone=..&ignore={id}
it('requires name_ar and phone, and a 15-digit trn when given');
it('creates a client from the quick-add modal as JSON');                                // 201 {id,name}; 422 with errors.name_ar
it('searches clients by name, phone and trn in the table and in global search');        // search '123 4567' matches stored '0501234567' via phone_normalized
it('filters clients by type, source and emirate');
it('soft deletes a client without linked records and blocks one with records');        // block covered again in Task 2 once leads exist
it('shows the client page with its tabs and attachments');                              // assertSee client name, tab labels
```

- [ ] **Step 2: Run** `php artisan test --filter="ClientsTest|PhoneTest"` — expected: FAIL (classes missing).
- [ ] **Step 3: Implement** `Phone`, enum, models, migrations, factory, provider registration (permissions `clients`, menu item `clients.clients` icon `users-group` route `clients.index` permission `clients.view`, `TrashRegistry`, `ClientSearchProvider` on name_ar, name_en, phone_normalized, email, trn — the term is also normalized when it looks like a phone).
- [ ] **Step 4: Implement** requests, controllers, `ClientsTable` (search `name_ar`, `name_en`, `phone_normalized`, `email`, `trn`; filters type, source, emirate, created date; columns name, type, phone, email, emirate, created), views. The form warns under the phone field (via `clients.duplicates`, debounced 400 ms) without blocking the save.
- [ ] **Step 5: Run** the filter — expected: PASS. Run the whole suite — expected: PASS.
- [ ] **Step 6: Commit** `stage-1: clients`.

---

### Task 2: Leads (requests) and record scope

**Files:**
- Create: `app/Models/Concerns/HasRecordScope.php`, `app/Models/Lead.php` + migration, `database/factories/LeadFactory.php`, `app/Http/Controllers/LeadController.php`, `app/Http/Requests/SaveLeadRequest.php`, `app/Livewire/Tables/LeadsTable.php`, `app/Search/LeadSearchProvider.php`, `resources/views/leads/{index,form,show}.blade.php`, `lang/{ar,en}/leads.php`
- Modify: `app/Models/Client.php` (scope, deletion check), `app/Livewire/DataTable.php` (scope hook), `app/Providers/SalesServiceProvider.php`
- Test: `tests/Feature/Sales/LeadsTest.php`, `tests/Feature/Sales/RecordScopeTest.php`

**Interfaces:**
- Consumes: Task 1 `Client`, `Phone`, `SalesServiceProvider`; `NumberGenerator::next(DocumentType::Lead)`; `Notifier::toUser(.., NotificationType::Assigned, ..)`.
- Produces:
  - `HasRecordScope` trait: abstract `protected static function assigneeColumn(): string`; `scopeVisibleTo(Builder $q, User $user): Builder` — no constraint when `$user->seesAllRecords()`, otherwise `where(assigneeColumn, $user->id)`; `isVisibleTo(User $user): bool`.
  - `DataTable::query()` results pass through `visibleTo(auth()->user())` when the model uses `HasRecordScope` — applied inside `filteredQuery()`, so rows, totals, export, delete-all and trash all respect it.
  - `Client::scopeVisibleTo`: for non-"all" users, clients that have a lead assigned to the user (Tasks 4 and 7 add `orWhereHas` for visits and quotations).
  - Permissions: `leads` → all actions.
  - `leads`: `number` (unique), `client_id` (nullable FK), `contact_name`, `contact_phone`, `contact_phone_normalized` (indexed), `contact_email` (nullable), `service_id` (nullable FK lookup), `facility_type` (nullable string 100), `project_state` (nullable string 100), `emirate_id`, `area_id` (nullable FK lookup), `area_text` (nullable), `area_m2` (nullable decimal 10,2), `expected_start` (nullable string 100), `description` (nullable text), `source_id` (FK lookup), `status_id` (FK lookup, lead_status), `assigned_to` (nullable FK users), `follow_up_at` (nullable datetime, indexed), `rejection_reason_id` (nullable FK lookup), `rejection_note` (nullable text), `external_id` (nullable string 50, unique), `created_by`, timestamps, `deleted_at`; indexes (`status_id`, `assigned_to`, `created_at`).
  - `Lead::statusCode(): ?string` (the lookup `code`), `Lead::isClosed(): bool` (`won` or `lost`).
  - New leads get status code `new` and the `NumberGenerator` number; assigning a user (create or change) sends `NotificationType::Assigned` with params `['number' => $lead->number]` and url `route('leads.show', $lead)`, not to the acting user themself.
  - Routes: resource `leads` (no destroy), `leads.attachments.store`.
  - `LeadsTable`: search `number`, `contact_name`, `contact_phone_normalized`, `client.name_ar`; filters status (lookup, coloured badge), service, source, assigned user, emirate, `overdue` boolean (`follow_up_at < now()` and not closed), created date range; columns number, contact/client, service, status, assigned, follow-up, created.
  - `LookupUsage` checks registered for `service`, `lead_status`, `lead_source`, `rejection_reason`, `emirate`, `area` against leads (and clients for source/emirate/area).

- [ ] **Step 1: Write the failing tests**

```php
// tests/Feature/Sales/LeadsTest.php
it('creates a lead with number LD-2026-0001 and status new');                    // Carbon::setTestNow('2026-11-14')
it('accepts a lead for an existing client or for new contact data');
it('notifies the assigned user, not the acting user');                           // assign to other → 1 notification; assign to self → 0
it('filters overdue follow-ups and excludes closed leads');
it('searches by number, name and a phone written differently');
it('blocks deleting a client that has a lead');                                  // deletionBlockedReason === __('clients.has_records')
it('refuses to delete a lookup used by a lead');                                 // service in use → Lookup::deletionBlockedReason not null

// tests/Feature/Sales/RecordScopeTest.php  (engineer = scope assigned, has leads.* permissions)
it('lists only leads assigned to an assigned-scope user');
it('returns 404 on another user\'s lead page and on its media', function () { /* leads.show, media.show of an attachment on that lead */ });
it('keeps delete all and export inside the user\'s own leads');                 // deleteAll removes own 2, leaves other 3; export has 2 data rows
it('hides other users\' leads from global search');
it('shows every lead to a user with an all-scope role');
```

- [ ] **Step 2: Run** `php artisan test --filter="LeadsTest|RecordScopeTest"` — expected: FAIL.
- [ ] **Step 3: Implement** trait, migration, model (`HasAttachments`, `LogsModelActivity`, `SoftDeletes`, `GuardsDeletion` — blocked when it has a quotation, extended in Task 7), factory, `DataTable` hook.
- [ ] **Step 4: Implement** request, controller (every action: permission check then `isVisibleTo` → 404), table, search provider, views, provider registrations, `Client` scope and deletion extension.
- [ ] **Step 5: Run** the filter and the whole suite — expected: PASS.
- [ ] **Step 6: Commit** `stage-1: leads and record scope`.

---

### Task 3: Lead follow-ups and actions

**Files:**
- Create: `app/Enums/FollowUpType.php`, `app/Models/LeadFollowUp.php` + migration, `app/Actions/Leads/{AssignLead,CloseLeadAsLost,EnsureClientForLead}.php`, `app/Http/Controllers/LeadActionController.php`, `app/Http/Requests/{StoreFollowUpRequest,CloseLeadRequest,AssignLeadRequest,ScheduleFollowUpRequest}.php`, `resources/views/leads/partials/{follow-ups,actions}.blade.php`
- Modify: `resources/views/leads/show.blade.php`, `lang/{ar,en}/leads.php`
- Test: `tests/Feature/Sales/LeadActionsTest.php`

**Interfaces:**
- Consumes: Task 2 `Lead`, Task 1 `Client`, `Phone`, `ActivityLogger::record('status_changed', ...)`.
- Produces:
  - `FollowUpType`: `Call='call'`, `Message='message'`, `Note='note'`.
  - `lead_follow_ups`: `lead_id`, `type`, `body` (text), `user_id`, `happened_at` (datetime), timestamps.
  - `EnsureClientForLead::handle(Lead $lead, User $by): Client` — returns the linked client, or the client whose `phone_normalized` equals the lead's, or creates one from the contact data (type individual, source = lead source) and links it. Used by Task 4 (visit) and Task 7 (quotation) — spec §8.2 "عند أول تحويل".
  - `AssignLead::handle(Lead $lead, ?User $to, User $by): void`, `CloseLeadAsLost::handle(Lead $lead, int $reasonId, ?string $note, User $by): void` (status `lost`, logs `status_changed` with old/new codes).
  - `Lead::setStatusCode(string $code, User $by): void` — used by Tasks 4, 7, 8 to move the lead to `visit`, `quoted`, `won`; logs `status_changed`; never moves a `lost` lead.
  - Routes (all `leads.update` + visibility): `leads.follow-ups.store`, `leads.assign` (PATCH), `leads.schedule` (PATCH, `follow_up_at`), `leads.close` (PATCH, `rejection_reason_id`, `rejection_note`), `leads.reopen` (PATCH, back to `contacted`).

- [ ] **Step 1: Write the failing tests**

```php
it('adds a call, message and note to the follow-up log with the author');
it('closes a lead as lost with a reason and logs the status change');           // activity event status_changed, properties old 'new' attributes 'lost'
it('requires a rejection reason to close');
it('reopens a lost lead to contacted');
it('schedules the next follow-up and clears the overdue flag');
it('creates the client from the lead data on first conversion');
it('reuses an existing client with the same phone instead of creating one');   // existing '+971501234567', lead '0501234567'
it('does not move a lost lead forward');                                         // setStatusCode('quoted') on lost → still lost
```

- [ ] **Step 2: Run** `php artisan test --filter=LeadActionsTest` — expected: FAIL.
- [ ] **Step 3: Implement** enum, model, migration, actions, `Lead::setStatusCode`.
- [ ] **Step 4: Implement** controller, requests, partials (timeline of follow-ups with a quick form; action buttons in the record header: assign, schedule, book visit → Task 4 route when it exists, new quotation → Task 7 route when it exists, close as lost via modal).
- [ ] **Step 5: Run** the filter and the whole suite — expected: PASS.
- [ ] **Step 6: Commit** `stage-1: lead follow-ups and actions`.

---

### Task 4: Site visits and the engineer screen

**Files:**
- Create: `app/Enums/SiteVisitStatus.php`, `app/Models/SiteVisit.php` + migration, `database/factories/SiteVisitFactory.php`, `app/Http/Controllers/{SiteVisitController,MyVisitsController}.php`, `app/Http/Requests/{SaveSiteVisitRequest,RecordVisitResultRequest}.php`, `app/Livewire/Tables/SiteVisitsTable.php`, `resources/views/site-visits/{index,form,show,result}.blade.php`, `resources/views/site-visits/my.blade.php`, `lang/{ar,en}/visits.php`
- Modify: `app/Providers/SalesServiceProvider.php`, `app/Models/Client.php` (scope, linked relations), `resources/views/leads/partials/actions.blade.php`
- Test: `tests/Feature/Sales/SiteVisitsTest.php`

**Interfaces:**
- Consumes: `HasRecordScope` (assignee column `engineer_id`), `EnsureClientForLead`, `Lead::setStatusCode('visit')`, `NumberGenerator::next(DocumentType::SiteVisit)`, `Notifier`, `DueTasks::register`, `<x-uploader :camera="true">`.
- Produces:
  - `SiteVisitStatus`: `Scheduled='scheduled'`, `Done='done'`, `Cancelled='cancelled'`; `label()`, `color()` (`info`, `success`, `neutral`).
  - `site_visits`: `number` (unique), `lead_id` (nullable FK), `client_id` (FK), `scheduled_at` (datetime, indexed), `engineer_id` (FK users, indexed), `address` (nullable text), `map_url` (nullable, `url`, https only), `status` (indexed), `notes` (nullable text), `measurements` (nullable text), `result_at` (nullable datetime), `created_by`, timestamps, `deleted_at`.
  - Creating a visit from a lead calls `EnsureClientForLead` and `setStatusCode('visit')`; assigning an engineer notifies them (`Assigned`).
  - `my-visits` (GET `/my/visits`, any user with `site_visits.view`): today's and upcoming scheduled visits of the signed-in engineer as large cards, each with a "record result" button opening `site-visits.result` (mobile-first, one form: status done/cancelled, notes, measurements, camera uploads, sticky save bar at the bottom).
  - Due task `visit-today` every 60 minutes: for every scheduled visit whose `scheduled_at` is today, `Notifier::toUser($engineer, NotificationType::VisitToday, $visit, ['number' => $visit->number], route('site-visits.show', $visit), 'visit-'.$date)`.
  - `SiteVisitsTable`: search number, client name, address; filters status, engineer, service (through lead), date range on `scheduled_at`.
  - Permissions `site_visits` → all actions; menu item `visits.visits` icon `map-pin`; a second menu item `visits.my_visits` icon `calendar` route `my-visits`.

- [ ] **Step 1: Write the failing tests**

```php
it('books a visit from a lead, creates the client and moves the lead to visit');
it('notifies the engineer when assigned');
it('shows only my scheduled visits of today and later on my visits');           // yesterday's and other engineer's are absent
it('records a result with photos from the engineer screen');                    // status done, result_at set, media count 1
it('lets an engineer open only their own visits');                              // 404 on another engineer's visit
it('sends the visit-today notification once per day');                          // runDue twice same day → 1; next day another visit → 1 more
it('rejects a map link that is not https');
```

- [ ] **Step 2: Run** `php artisan test --filter=SiteVisitsTest` — expected: FAIL.
- [ ] **Step 3: Implement** enum, model, migration, factory, provider registrations (permission, menu, trash, search provider on number/client, lookup usage, due task).
- [ ] **Step 4: Implement** controllers, requests, table, views; the lead action "book visit" links to `site-visits.create?lead={id}`.
- [ ] **Step 5: Run** the filter and the whole suite — expected: PASS. Check `/my/visits` and the result form at 390 px: no horizontal scroll, buttons at the bottom.
- [ ] **Step 6: Commit** `stage-1: site visits`.

---

### Task 5: Price items and Excel import

**Files:**
- Create: `app/Models/PriceItem.php` + migration, `database/factories/PriceItemFactory.php`, `app/Http/Controllers/{PriceItemController,PriceItemImportController}.php`, `app/Http/Requests/{SavePriceItemRequest,ImportPriceItemsRequest}.php`, `app/Actions/ImportPriceItems.php`, `app/Livewire/Tables/PriceItemsTable.php`, `resources/views/price-items/{index,form-modal,import}.blade.php`, `lang/{ar,en}/price_items.php`
- Modify: `app/Providers/SalesServiceProvider.php`
- Test: `tests/Feature/Sales/PriceItemsTest.php`

**Interfaces:**
- Produces:
  - `price_items`: `service_id` (nullable FK lookup), `description_ar` (text), `description_en` (nullable text), `unit_id` (FK lookup), `default_price` decimal(14,2), `is_active` (default true), timestamps, `deleted_at`. Accessor `description` falls back to Arabic.
  - Add / edit in a modal by AJAX (same pattern as `lookups/form-modal`).
  - `ImportPriceItems::handle(string $path, User $by): ImportResult` with `int $created`, `int $updated`, `list<string> $errors` (`"row 7: unit not found"` translated). Columns by header: `description_ar` (required), `description_en`, `service` (Arabic or English name), `unit` (Arabic or English name), `price` (number ≥ 0), `active` (1/0, default 1). A row whose `description_ar` + `unit` already exists updates its price; nothing is imported when more than 50% of rows fail. Reads with `SimpleExcelReader`.
  - `price-items.import-template` (GET) downloads an XLSX with the header row.
  - `price-items.search` (GET `?q=&service_id=`) → JSON `[{id, description, unit_id, unit, price}]`, active only, 20 max, LIKE via `App\Support\Like` — consumed by Task 7.
  - Permissions `price_items` → all actions; menu item `price_items.price_items` icon `list-numbers`; `LookupUsage` for `service` and `unit`.

- [ ] **Step 1: Write the failing tests**

```php
it('creates and edits a price item from the modal as JSON');
it('imports rows, updates an existing item price and reports bad rows');       // 3 rows: new, existing (price changed), unknown unit → created 1, updated 1, errors ['row 4: ...']
it('imports nothing when most rows fail');
it('rejects an import file that is not xlsx or csv');
it('finds active items for the quotation picker by text and service');
it('refuses to delete a unit used by a price item');
```

- [ ] **Step 2: Run** `php artisan test --filter=PriceItemsTest` — expected: FAIL.
- [ ] **Step 3: Implement** model, migration, factory, action, controllers, requests, table (search descriptions; filters service, unit, active, price range; `default_price` column `alignEnd()` with `money()`), views.
- [ ] **Step 4: Run** the filter and the whole suite — expected: PASS.
- [ ] **Step 5: Commit** `stage-1: price items`.

---

### Task 6: Document and payment templates

**Files:**
- Create: `app/Settings/TemplateSettings.php` + settings migration, `app/Http/Controllers/TemplateSettingsController.php`, `app/Http/Requests/Settings/TemplateSettingsRequest.php`, `resources/views/settings/templates.blade.php`
- Modify: `config/settings.php`, `resources/views/settings/index.blade.php`, `lang/{ar,en}/settings.php`
- Test: `tests/Feature/Sales/TemplateSettingsTest.php`

**Interfaces:**
- Produces `TemplateSettings` (group `templates`; no nested `@var` docblocks, see Stage 0 ruling):
  - `quotation_terms_ar`, `quotation_terms_en` (text) — default: four short numbered terms (validity, payment per schedule, VAT included, works outside the items are a variation).
  - `quotation_footer_ar`, `quotation_footer_en` (nullable text).
  - `whatsapp_quotation_ar`, `whatsapp_quotation_en` — placeholders `:client`, `:number`, `:total`, `:link`; default Arabic `السيد :client، مرفق عرض السعر رقم :number بقيمة :total. :link`.
  - `payment_templates` array of `['name_ar' => string, 'name_en' => ?string, 'lines' => [['label_ar' => string, 'label_en' => ?string, 'percent' => string]]]` — default one template `30 / 40 / 30` (مقدم، عند منتصف الأعمال، عند التسليم). Lines of a template must sum to 100.00.
- Routes: `settings.templates` (GET/PUT `/settings/templates`, `settings.manage`); the payment templates editor adds/removes templates and lines in the browser (jQuery, `window.I18N` strings), with a live sum per template.

- [ ] **Step 1: Write the failing tests**

```php
it('has the default 30/40/30 payment template and Arabic WhatsApp text');
it('saves terms and a new payment template');
it('rejects a payment template whose lines do not sum to 100');
it('rejects a WhatsApp template without the :link placeholder');
it('returns 403 without settings.manage');
```

- [ ] **Step 2: Run** `php artisan test --filter=TemplateSettingsTest` — expected: FAIL.
- [ ] **Step 3: Implement** settings class and migration, request (sum check with `bccomp` on 2 decimals), controller, view, settings tile (`settings.groups.templates`, icon `file-text`).
- [ ] **Step 4: Run** the filter and the whole suite — expected: PASS.
- [ ] **Step 5: Commit** `stage-1: document and payment templates`.

---

### Task 7: Quotations — totals, form and list

**Files:**
- Create: `app/Enums/{QuotationStatus,DiscountType}.php`, `app/Models/{Quotation,QuotationItem,QuotationPayment}.php` + migrations, `database/factories/{QuotationFactory,QuotationItemFactory}.php`, `app/Services/QuotationCalculator.php`, `app/Support/QuotationTotals.php`, `app/Actions/Quotations/SaveQuotation.php`, `app/Http/Controllers/QuotationController.php`, `app/Http/Requests/SaveQuotationRequest.php`, `app/Livewire/Tables/QuotationsTable.php`, `app/Search/QuotationSearchProvider.php`, `resources/views/quotations/{index,form,show}.blade.php`, `resources/views/quotations/partials/*`, `resources/js/quotation-form.js`, `lang/{ar,en}/quotations.php`
- Modify: `vite.config.js`, `app/Providers/SalesServiceProvider.php`, `app/Models/{Client,Lead}.php` (scope, deletion), `resources/views/leads/partials/actions.blade.php`, `resources/views/clients/show.blade.php`
- Test: `tests/Unit/QuotationCalculatorTest.php`, `tests/Feature/Sales/QuotationsTest.php`

**Interfaces:**
- Consumes: `NumberGenerator::next(DocumentType::Quotation)`, `FinanceSettings` (`vat_rate`, `decimals`, `quotation_validity_days`), `TemplateSettings` (terms, payment templates), `price-items.search`, `EnsureClientForLead`, `Lead::setStatusCode('quoted')`, `HasRecordScope` (assignee `assigned_to`), `<x-client-quick-add />`.
- Produces:
  - `QuotationStatus`: `Draft`, `Sent`, `Accepted`, `Rejected`, `Expired` (values lower-case); `label()`, `color()`; `Quotation::displayStatus(): QuotationStatus` returns `Expired` when status is draft or sent and `valid_until` is before today (spec §8.5: expired is computed).
  - `DiscountType`: `Amount='amount'`, `Percent='percent'`.
  - `quotations`: `number` (unique), `revision` (int default 0), `parent_id` (nullable FK self — the quotation this revises), `superseded_at` (nullable datetime), `client_id`, `lead_id` (nullable), `service_id` (nullable), `title_ar`, `title_en` (nullable), `location` (nullable), `emirate_id` (nullable), `issue_date` (date), `valid_until` (date), `assigned_to` (FK users), `status` (indexed), `discount_type` (nullable), `discount_value` decimal(14,2) default 0, `vat_rate` decimal(5,2), `subtotal`, `discount_amount`, `taxable_amount`, `vat_amount`, `total` (decimal 14,2), `terms_ar`, `terms_en` (nullable text), `notes` (nullable text), `sent_at`, `accepted_at`, `rejected_at` (nullable datetimes), `rejection_reason_id` (nullable), `rejection_note` (nullable), `created_by`, timestamps, `deleted_at`; indexes (`status`, `valid_until`), (`client_id`), (`assigned_to`), (`issue_date`).
  - `quotation_items`: `quotation_id`, `section_ar`, `section_en` (nullable — rows with the same section title are grouped), `price_item_id` (nullable FK), `description_ar`, `description_en` (nullable), `unit_id` (nullable FK lookup), `quantity` decimal(12,3), `unit_price` decimal(14,2), `line_total` decimal(14,2), `sort_order`.
  - `quotation_payments`: `quotation_id`, `label_ar`, `label_en` (nullable), `percent` decimal(5,2), `amount` decimal(14,2), `sort_order`.
  - `QuotationCalculator::calculate(array $lines, ?DiscountType $type, string $discount, string $vatRate, int $decimals): QuotationTotals` — `$lines` items `['quantity' => string, 'unit_price' => string]`; uses `bcmath` at scale 6 and rounds half up to `$decimals` at each stored value: `line_total = round(q × p)`, `subtotal = Σ line_total`, `discount_amount = round(subtotal × pct / 100)` or the amount, `taxable = subtotal − discount`, `vat = round(taxable × rate / 100)`, `total = taxable + vat`. Throws `InvalidArgumentException` for percent > 100, a negative value, or a discount amount above the subtotal.
  - `QuotationTotals` (readonly): `list<string> $lineTotals`, `string $subtotal`, `string $discountAmount`, `string $taxable`, `string $vat`, `string $total`; `paymentAmounts(list<string> $percents): list<string>` — last line absorbs rounding so amounts sum to `total` exactly.
  - `SaveQuotation::handle(array $data, User $by, ?Quotation $quotation = null): Quotation` — one transaction: number (create only), `vat_rate` from settings (create only), items, payments, totals from the calculator; ignores any total posted by the browser.
  - The form: header fields, items grouped in optional sections, item rows added from the price picker (Tom Select remote on `price-items.search`) or typed freely, drag sorting (SortableJS), quantities and prices with live totals in the browser (`quotation-form.js`, same rounding as the server, display only), discount amount/percent, payment schedule filled from a chosen template and editable (shows a warning when percents ≠ 100), terms prefilled from `TemplateSettings`. Sticky save bar.
  - Validation: at least one item; payment percents must sum to 100.00; `valid_until` ≥ `issue_date`; defaults `issue_date` today and `valid_until` = today + `quotation_validity_days`.
  - Routes: resource `quotations` (no destroy), `quotations.attachments.store`; `quotations.create?lead={id}` prefills client, service, title from the lead and on save calls `EnsureClientForLead` and `setStatusCode('quoted')`.
  - `QuotationsTable`: search number, client name, title; filters status (display status, so "expired" filters `valid_until < today` with draft/sent), service, assigned user, total range (`NumberRangeFilter`), issue date range, `expiring` boolean (draft/sent with `valid_until` within the `QuotationExpiring` rule days); `total` column `summable()` and `alignEnd()`.
  - Permissions `quotations` → all actions plus `approve` (accept a quotation, §6 "اعتماد عرض السعر"). Menu `quotations.quotations` icon `file-invoice`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Unit/QuotationCalculatorTest.php
it('computes lines, percent discount, VAT and total', function () {
    $t = app(QuotationCalculator::class)->calculate([['quantity' => '120.5', 'unit_price' => '45.00'], ['quantity' => '1', 'unit_price' => '3500']], DiscountType::Percent, '10', '5.00', 2);
    expect($t->lineTotals)->toBe(['5422.50', '3500.00'])->and($t->subtotal)->toBe('8922.50')
        ->and($t->discountAmount)->toBe('892.25')->and($t->taxable)->toBe('8030.25')
        ->and($t->vat)->toBe('401.51')->and($t->total)->toBe('8431.76');
});
it('rounds to the decimals setting', fn () => /* decimals 3: q 1, p '10.0005' → line '10.001' */);
it('rejects a percent over 100, a negative quantity and an amount discount over the subtotal');
it('splits payments so the amounts add up to the total exactly', fn () => /* total 1000.01, [30,40,30] → ['300.00','400.00','300.01'] */);

// tests/Feature/Sales/QuotationsTest.php
it('creates a quotation with number QT-2026-0001, items, payments and server totals');
it('ignores totals posted by the browser');                                      // post total '1' → stored total equals calculator result
it('keeps the VAT rate of an existing quotation after the setting changes');     // vat 5 → create → set 10 → update items → vat_rate still 5.00
it('requires payment percents that sum to 100 and at least one item');
it('creates a quotation from a lead, creating the client and moving the lead to quoted');
it('shows expired for a sent quotation past its validity and filters it');
it('filters by total range and sums the total column over the filtered result');
it('lets an assigned-scope user see only their quotations, also in search');
it('blocks deleting a client or lead that has a quotation');
```

- [ ] **Step 2: Run** `php artisan test --filter="QuotationCalculatorTest|QuotationsTest"` — expected: FAIL.
- [ ] **Step 3: Implement** enums, `QuotationTotals`, `QuotationCalculator` → run the unit filter → PASS.
- [ ] **Step 4: Implement** models, migrations, factories, `SaveQuotation`, request, controller (create, store, show, edit, update — edit and update only while draft or sent and not superseded), provider registrations, `Client`/`Lead` scope and deletion extensions.
- [ ] **Step 5: Implement** table, search provider, views, `quotation-form.js` (add to `vite.config.js`, loaded with `@push`), lead action and client tab links.
- [ ] **Step 6: Run** the filter and the whole suite — expected: PASS. Open the form at 390 px and 1440 px in both languages; item rows become stacked cards under 768 px.
- [ ] **Step 7: Commit** `stage-1: quotations`.

---

### Task 8: Quotation workflow — send, revise, copy, accept, reject, reminders

**Files:**
- Create: `app/Actions/Quotations/{MarkQuotationSent,ReviseQuotation,CopyQuotation,AcceptQuotation,RejectQuotation}.php`, `app/Http/Controllers/QuotationActionController.php`, `app/Http/Requests/RejectQuotationRequest.php`, `resources/views/quotations/partials/{actions,revisions,reject-modal}.blade.php`
- Modify: `app/Models/Quotation.php`, `app/Providers/SalesServiceProvider.php`, `resources/views/quotations/show.blade.php`, `lang/{ar,en}/quotations.php`
- Test: `tests/Feature/Sales/QuotationWorkflowTest.php`

**Interfaces:**
- Consumes: Task 7 models and `SaveQuotation`, `Lead::setStatusCode`, `ActivityLogger::record('status_changed', ...)`, `Notifier`, `NotificationSettings::rule()`, `DueTasks::register`.
- Produces:
  - `Quotation::isLocked(): bool` — true when accepted, rejected, or superseded. Every write route (update, actions below except copy) refuses a locked quotation with the flash error `quotations.locked` (and 403 for JSON).
  - `MarkQuotationSent` (draft → sent, `sent_at`), `RejectQuotation` (draft/sent → rejected with reason), `AcceptQuotation` (draft/sent → accepted, `accepted_at`; requires `quotations.approve`; moves the lead to `won`), each logs `status_changed`.
  - `ReviseQuotation::handle(Quotation $q, User $by): Quotation` — only for draft/sent and not superseded: new row with the same base number plus `-R{n}` (`QT-2026-0001-R1`, then `-R2` from either the original or R1), `revision = n`, `parent_id` = root quotation id, items and payments copied, status draft; the old one gets `superseded_at` and becomes read-only. No new `NumberGenerator` number is used.
  - `CopyQuotation::handle(Quotation $q, User $by): Quotation` — a new quotation with a new number, draft, today's dates and the current VAT setting, totals recomputed.
  - Show page: revisions list (all rows sharing the root), action buttons by state and permission, reject modal.
  - Due tasks (hourly): `quotation-no-response` — sent quotations with `sent_at` older than the rule days and still sent → `Notifier::toUser($assigned, QuotationNoResponse, ...)`, dedupe key `'no-response'`; `quotation-expiring` — draft/sent with `valid_until` within the rule days → `QuotationExpiring` with `['number', 'date' => fmt_date(valid_until)]`, dedupe key `'expiring-'.valid_until`.
  - Converting an accepted quotation into a project is Stage 2; the show page reserves the action slot.

- [ ] **Step 1: Write the failing tests**

```php
it('marks a draft as sent');
it('creates revision R1 then R2 and makes the previous one read-only');       // numbers QT-2026-0001-R1, QT-2026-0001-R2; PUT on R1 after R2 → error quotations.locked
it('does not consume a document number for a revision');                       // NumberGenerator::preview unchanged
it('copies a quotation with a new number and the current VAT');
it('accepts only with quotations.approve and moves the lead to won');
it('refuses every change to an accepted quotation', function () { /* update, reject, accept again, revise → refused; copy → allowed; show → 200 */ });
it('rejects with a reason and logs the status change');
it('sends the no-response reminder once after the rule days');
it('sends the expiring reminder once per validity date');
```

- [ ] **Step 2: Run** `php artisan test --filter=QuotationWorkflowTest` — expected: FAIL.
- [ ] **Step 3: Implement** `isLocked`, the five actions, the controller (permission → visibility → lock checks), requests, partials, due tasks.
- [ ] **Step 4: Run** the filter and the whole suite — expected: PASS.
- [ ] **Step 5: Commit** `stage-1: quotation workflow`.

---

### Task 9: Quotation PDF, print and WhatsApp

**Files:**
- Create: `app/Services/Pdf/PdfRenderer.php`, `resources/views/pdf/{layout,quotation}.blade.php`, `resources/fonts/pdf/` (see Step 1), `app/Http/Controllers/{QuotationPdfController,PublicDocumentController}.php`, `app/Support/WhatsApp.php`
- Modify: `composer.json` (`mpdf/mpdf ^8.2`), `app/Providers/SalesServiceProvider.php`, `resources/views/quotations/partials/actions.blade.php`, `lang/{ar,en}/quotations.php`, `routes/web.php`
- Test: `tests/Feature/Sales/QuotationPdfTest.php`, `tests/Unit/WhatsAppTest.php`

**Interfaces:**
- Consumes: `CompanySettings` (logo, stamp, signature, TRN, bank data, names, address, phones), `AppearanceSettings` (fonts, colours), `TemplateSettings`, `SystemSettings::public_link_hours`, `ActivityLogger::record('printed', ...)`.
- Produces:
  - `PdfRenderer::render(string $view, array $data, string $language): string` — returns PDF bytes. `$language` in `ar|en|both`; `ar` and `both` set `directionality rtl`. Font: Cairo or Tajawal TTF from `resources/fonts/pdf/` following `AppearanceSettings::primary_font` (headings follow `heading_font`); when those files are absent it falls back to mPDF's bundled `xbriyaz` (Arabic) and `dejavusans` (Latin). Temp dir `storage/app/mpdf`. Western digits in both languages.
  - `quotations.pdf` (GET `?lang=ar|en|both&download=0|1`, permission `quotations.print` + visibility) streams the PDF inline (or as attachment) named `{number}.pdf`, logs `printed`.
  - `quotations.share` (POST) → JSON `{url, whatsapp}`: `url` is `URL::temporarySignedRoute('public.quotation', now()->addHours(public_link_hours), ...)`; `whatsapp` is `WhatsApp::link($phone, $message)`.
  - `public.quotation` (GET `/d/q/{quotation}`, middleware `signed`, no auth) streams the same PDF in the client's chosen language; an expired or altered link returns 403.
  - `WhatsApp::link(?string $phone, string $message): string` — `https://wa.me/{digits}?text={rawurlencode}` using `Phone::normalize` of the client's `whatsapp`, else `phone`; without a phone `https://wa.me/?text=…`. `WhatsApp::message(Quotation $q, string $language, string $url): string` fills `:client`, `:number`, `:total` (`money()`), `:link` from `TemplateSettings`.
  - Quotation PDF content: company header (logo, names, TRN), title and number with revision, client block, items grouped by section with totals row, discount, VAT, total, payment schedule, terms, footer with bank data, stamp and signature. Amounts aligned in one column.

- [ ] **Step 1: PDF fonts.** Cairo and Tajawal TTF files are not in the project (`@fontsource` ships only WOFF). Ask the user for permission to download `Cairo-Regular.ttf`, `Cairo-Bold.ttf`, `Tajawal-Regular.ttf`, `Tajawal-Bold.ttf` (SIL OFL) from the `google/fonts` GitHub repository into `resources/fonts/pdf/`, stating source and size. If declined, keep the bundled fallback and record it in PROGRESS.md.
- [ ] **Step 2: Install** `composer require mpdf/mpdf:^8.2`.
- [ ] **Step 3: Write the failing tests**

```php
// tests/Unit/WhatsAppTest.php
it('builds a wa.me link with the normalized phone and encoded Arabic text');   // '0501234567' → starts 'https://wa.me/971501234567?text=' and decodes back to the message
it('fills the quotation template placeholders');

// tests/Feature/Sales/QuotationPdfTest.php
it('renders the quotation PDF in Arabic, English and both', function (string $lang) { /* 200, Content-Type application/pdf, body starts with %PDF */ })->with(['ar', 'en', 'both']);
it('logs printing in the activity log');
it('needs quotations.print and visibility to print');
it('serves the PDF on a valid signed link without login and refuses an expired one'); // travel public_link_hours + 1 → 403
it('returns a share url and a WhatsApp link');
```

- [ ] **Step 4: Run** `php artisan test --filter="QuotationPdfTest|WhatsAppTest"` — expected: FAIL.
- [ ] **Step 5: Implement** `PdfRenderer`, `WhatsApp`, controllers, routes (the public route outside the `auth` group), views, action buttons (print menu with the three languages; "send by WhatsApp" opens the link in a new tab after `quotations.share`).
- [ ] **Step 6: Run** the filter and the whole suite — expected: PASS. Open one PDF in each language and check: Arabic letters joined, numbers western, amounts aligned, stamp and signature placed.
- [ ] **Step 7: Commit** `stage-1: quotation pdf and whatsapp`.

---

### Task 10: Website request API and fetch

**Files:**
- Create: `app/Settings/IntegrationSettings.php` + settings migration, `app/Http/Controllers/Api/WebsiteLeadController.php`, `app/Http/Middleware/VerifyWebsiteKey.php`, `app/Http/Requests/Api/WebsiteLeadRequest.php`, `app/Actions/Leads/ReceiveWebsiteLead.php`, `app/Services/WebsiteSync.php`, `app/Http/Controllers/IntegrationController.php`, `resources/views/settings/integration.blade.php`, `routes/api.php`, `docs/integration/website-link.md`
- Modify: `bootstrap/app.php` (api routes, `throttle:website` limiter), `config/settings.php`, `resources/views/settings/index.blade.php`, `resources/views/leads/index.blade.php` (fetch button), `lang/{ar,en}/settings.php`, `lang/{ar,en}/leads.php`
- Test: `tests/Feature/Sales/WebsiteApiTest.php`, `tests/Feature/Sales/WebsiteSyncTest.php`

**Interfaces:**
- Consumes: `Lead`, `Phone`, `UploadValidator`, `AttachUploads` pattern (media added from uploaded files after `UploadValidator::kind()`), `Notifier::toRoles(NotificationType::LeadFromWebsite, ...)`, lead source with code `website`, status `new`.
- Produces:
  - `IntegrationSettings` (group `integration`): `api_key_hash` (nullable; the key is shown once when generated, only its SHA-256 is stored), `website_url` (nullable https URL), `last_sync_at` (nullable string ISO), `last_sync_status` (nullable string), `last_sync_count` (int 0).
  - `POST /api/v1/website/leads` — header `X-Api-Key`, rate limit 30 per minute per IP, JSON or multipart. Body: `external_id` (required, the website request number such as `QR-0042`), `name`, `phone` (required), `email`, `service` (website service name; matched to a `service` lookup by Arabic or English name, else null and kept in the description), `facility_type`, `project_state`, `emirate` (matched by name), `area_text`, `area_m2`, `start_when`, `description`, `preferred_contact`, `locale`, `files[]` (optional, each checked by `UploadValidator`). Responses: `201 {id, number}` new; `200 {id, number}` when `external_id` already exists (no changes, no notification); `401` wrong or missing key; `422` validation; `429` rate limit.
  - `ReceiveWebsiteLead::handle(array $data, array $files): array{lead: Lead, created: bool}` — inside a transaction with the unique `external_id` as the guard (a unique-violation race returns the existing lead); notifies sales only when created.
  - `WebsiteSync::fetch(): int` — `GET {website_url}/api/system/quote-requests/unsynced` with `X-Api-Key`, expects `{data: [payload as above, files as https URLs]}`, passes each through `ReceiveWebsiteLead`, then `POST {website_url}/api/system/quote-requests/synced` with the received `external_id`s; stores `last_sync_*`. Remote files are downloaded only from `website_url`'s host, max size by `UploadSettings`.
  - `settings.integration` (GET/PUT, `settings.manage`): website URL, "generate new key" (shows the key once), last sync status. `leads.fetch-website` (POST, `leads.create`) runs `WebsiteSync::fetch()` and flashes the count.
  - `docs/integration/website-link.md`: exact changes for the website project (send after the response with `dispatch(...)->afterResponse()`, `synced_at` column on `quote_requests`, the two `api/system/...` endpoints guarded by the same key, payload mapping from `QuoteRequest` fields).

- [ ] **Step 1: Write the failing tests**

```php
// WebsiteApiTest
it('creates a lead with source website, status new and notifies sales');
it('returns the same lead for a repeated external_id without a second notification or attachment');
it('rejects a missing or wrong key with 401');
it('rate limits after 30 requests a minute');
it('stores valid files and rejects a fake image with 422');
it('matches service and emirate by name and keeps unknown ones in the description');

// WebsiteSyncTest (Http::fake)
it('fetches unsynced requests, creates leads and confirms them to the website');
it('records a failed sync without throwing');                                   // website 500 → last_sync_status error text, count 0
it('downloads files only from the website host');                               // file URL on another host is skipped
it('generates a key once and stores only its hash');
```

- [ ] **Step 2: Run** `php artisan test --filter="WebsiteApiTest|WebsiteSyncTest"` — expected: FAIL.
- [ ] **Step 3: Implement** settings, middleware (`hash_equals` on SHA-256), request, action, controller, routes and limiter.
- [ ] **Step 4: Implement** `WebsiteSync`, integration screen, fetch button, the instructions document.
- [ ] **Step 5: Run** the filter and the whole suite — expected: PASS.
- [ ] **Step 6: Commit** `stage-1: website request api`.

---

### Task 11: Demo data and stage gate

**Files:**
- Create: `database/seeders/SalesDemoSeeder.php`
- Modify: `database/seeders/DemoSeeder.php` (calls it), `docs/plans/PROGRESS.md`
- Test: `tests/Feature/Sales/SalesDemoSeederTest.php`

**Interfaces:**
- `SalesDemoSeeder` (local only): 40 clients (individuals and companies with contacts, realistic Emirati names and Abu Dhabi / Dubai areas), 80 leads across all statuses with follow-ups, 30 site visits (some today for the demo engineers), 60 price items across the services, 35 quotations across all states including revisions and expired ones.

- [ ] **Step 1: Write the failing test** — `it('seeds sales demo data that renders every sales list')`: run the seeder, then as super-admin GET `clients.index`, `leads.index`, `site-visits.index`, `price-items.index`, `quotations.index`, one `quotations.show` and its PDF → all 200.
- [ ] **Step 2: Run** `php artisan test --filter=SalesDemoSeederTest` — expected: FAIL.
- [ ] **Step 3: Implement** the seeder.
- [ ] **Step 4: Quality gates.** Run: `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G && php artisan test && npm run build`. Expected: all succeed, `storage/logs/laravel.log` has no new errors.
- [ ] **Step 5: Visual pass** at 390 px and 1440 px, Arabic and English: clients list, client page, lead page, my visits, visit result form, quotation form, quotation page, quotation PDF. No horizontal scroll at 375 px (measure `scrollWidth`).
- [ ] **Step 6: Update `docs/plans/PROGRESS.md`** — Stage 1 delivered, decisions, gaps, next: Stage 2 plan.
- [ ] **Step 7: Commit** `stage-1: demo data and stage gate`.
