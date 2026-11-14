# Stage 2 — Projects Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Projects created from an accepted quotation or directly, with stages and progress, payment schedule, variation orders, handover, warranty and maintenance requests.

**Architecture:** Same module pattern as Stage 1 (model, Form Requests, thin controller, `DataTable`, Blade, feature tests). Business rules in `app/Actions/Projects` and `app/Services` (progress, contract value, installment due states). One `ProjectsServiceProvider` joins the Stage 0 registries, filling static registries once per process like `SalesServiceProvider`. Money that later stages produce (invoiced, collected, cost) lives in summary columns on `projects` that Stage 2 creates at zero and Stage 3 keeps up to date (spec §11).

**Tech Stack:** As Stages 0–1 (mPDF for the handover record). No new package.

**Spec:** `docs/specs/2026-11-12-class-skilled-system-design.md` §7 (stage templates), §8.5 (accept and convert), §8.6, §8.7, §8.8, §8.9, §8.18, §5.5 (StageLate, ProjectEnding, WarrantyEnding, InstallmentDue), §5.10 (handover record PDF), §6 (scope).

## Global Constraints

- Everything in `CLAUDE.md` → Global Constraints applies unchanged.
- No new composer or npm package.
- Money `decimal(14,2)`, quantities `decimal(12,3)`, percentages and weights `decimal(5,2)`; all totals on the server through `bcmath` (reuse `QuotationCalculator::round`).
- Numbers from `NumberGenerator`: `DocumentType::Project` (PRJ), `Variation` (VO), `Maintenance` (MR).
- Every new lang group goes into `TranslationCatalog::GROUPS`; replies to the user in Arabic (memory).
- Static registries are filled once per process (Stage 1 ruling); container singletons (search) on every boot.
- Commit at the end of every task with the message shown. Local commits only.
- Out of this stage: invoices, receipts, expenses, profitability and the figures that depend on them (Stage 3); daily reports and progress claims (Stage 4). Their summary columns and tab slots exist but stay empty.

## Review Focus

1. An "assigned only" engineer reaches a project only as its manager or a team member — in lists, pages, media, search, export, stage and installment actions, and the maintenance list. [Tasks 2, 3, 4, 8]
2. Converting the same accepted quotation twice (double click, two tabs) creates one project only. [Task 5]
3. Project progress follows the stage weights even when weights do not add up to 100 (they are normalised), and a project with no stages shows 0, not an error. [Task 3]
4. An approved variation changes the contract value exactly once; rejecting or re-approving does not apply it again; the installment sum warning uses the value after variations. [Tasks 4, 6]
5. Warranty end is computed from the handover date and months, month-end safe (31 January + 1 month = 28/29 February), and "inside warranty" for a maintenance request follows its own date. [Tasks 7, 8]

---

### Task 1: Stage templates

**Files:**
- Create: `app/Models/StageTemplate.php` + migration, `app/Http/Controllers/StageTemplateController.php`, `app/Http/Requests/SaveStageTemplatesRequest.php`, `resources/views/settings/stage-templates.blade.php`, `app/Providers/ProjectsServiceProvider.php` (add to `bootstrap/providers.php`), `lang/{ar,en}/projects.php`
- Modify: `resources/views/settings/index.blade.php`, `app/Support/TranslationCatalog.php`, `lang/{ar,en}/permissions.php`
- Test: `tests/Feature/Projects/StageTemplatesTest.php`

**Interfaces:**
- `stage_templates`: `service_id` (FK lookup, indexed), `name_ar`, `name_en` (nullable), `weight` decimal(5,2), `sort_order`, timestamps, `deleted_at`.
- `StageTemplate::forService(?int $serviceId): Collection` — ordered by `sort_order`; empty collection when none.
- Screen `settings.stage-templates` (GET `/settings/stage-templates?service=`, PUT same, `settings.manage`): pick a service, edit its rows (name ar/en, weight) with add, remove and drag order; live weight total shown. Saving replaces that service's rows. Weights must be > 0; the total is shown but not forced to 100 (progress normalises, Task 4).
- `LookupUsage` for `service` against `stage_templates`.
- Permissions registered by the provider: `projects` (all actions), `maintenance` (all actions except print).

- [ ] **Step 1: Failing tests** — `it('saves the stage template of a service in order')`, `it('rejects a stage with weight 0 or no Arabic name')`, `it('keeps other services untouched when one is saved')`, `it('returns 403 without settings.manage')`, `it('refuses to delete a service used by a template')`.
- [ ] **Step 2: Run** `php artisan test --filter=StageTemplatesTest` — expected FAIL.
- [ ] **Step 3: Implement** model, migration, provider (permissions, lookup usage), request, controller, view (SortableJS on rows), lang, settings tile (`settings.groups.stage_templates`, icon `list-check`).
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-2: stage templates`.

---

### Task 2: Projects core — model, scope, form, list, page

**Files:**
- Create: `app/Enums/{ProjectStatus,BillingMethod}.php`, `app/Models/{Project,ProjectItem}.php` + migrations (`projects`, `project_members`, `project_items`), `database/factories/ProjectFactory.php`, `app/Http/Controllers/ProjectController.php`, `app/Http/Requests/SaveProjectRequest.php`, `app/Livewire/Tables/ProjectsTable.php`, `app/Search/ProjectSearchProvider.php`, `resources/views/projects/{index,form,show}.blade.php`, `resources/views/projects/partials/*`, `resources/views/clients/tabs/projects.blade.php`, `routes/projects.php` (required from `routes/web.php` in the auth group)
- Modify: `app/Providers/ProjectsServiceProvider.php`, `app/Models/Client.php` (relation, linked relation, visibility rule), `lang/{ar,en}/projects.php`
- Test: `tests/Feature/Projects/ProjectsTest.php`, `tests/Feature/Projects/ProjectScopeTest.php`

**Interfaces:**
- `ProjectStatus`: `NotStarted='not_started'`, `InProgress='in_progress'`, `OnHold='on_hold'`, `Completed='completed'`, `Delivered='delivered'`, `Cancelled='cancelled'`; `label()`, `color()`.
- `BillingMethod`: `Installments='installments'`, `Claims='claims'` (spec §8.14 — chosen at creation; claims arrive in Stage 4).
- `projects`: `number` (unique), `title_ar`, `title_en` (nullable), `client_id` (FK), `quotation_id` (nullable FK, unique), `service_id` (nullable FK lookup), `emirate_id` (nullable FK lookup), `location` (nullable), `original_value` decimal(14,2), `variations_total` decimal(14,2) default 0, `contract_value` decimal(14,2) (original + approved variations), `vat_rate` decimal(5,2), `billing_method`, `start_date` (date, nullable), `planned_end_date` (date, nullable), `actual_end_date` (date, nullable), `manager_id` (FK users), `status` (indexed), `progress` decimal(5,2) default 0, `description` (nullable text), summary columns `invoiced_total`, `collected_total`, `cost_total` decimal(14,2) default 0 (Stage 3), `created_by`, timestamps, `deleted_at`; indexes (`status`, `planned_end_date`), (`manager_id`), (`client_id`).
- `project_members`: `project_id`, `user_id`, unique pair (the team).
- `project_items`: like `quotation_items` (section, description ar/en, unit, quantity, unit_price, line_total, sort_order) — the contract items used by variations and Stage 4 claims.
- `Project::scopeVisibleTo(Builder, User)`: all for `seesAllRecords()`, else `manager_id = user` or member of the team; `isVisibleTo(User)`. `DataTable::scopedQuery()` applies it automatically.
- `Project::daysLeft(): ?int` (negative when late; null without a planned end), `Project::isLate(): bool` (planned end passed and status not completed, delivered or cancelled).
- Direct creation form: client (remote select + quick add), titles, service, emirate, location, original value, VAT shown from settings (stored at creation), billing method, dates, manager, team (multi select), description. `contract_value` is computed, never posted.
- Show page: `<x-record-header>` with status, progress bar, days left or late, contract value, and figure slots for invoiced, collected, outstanding, cost, profit (shown as "—" until Stage 3 fills them; visible only with a later `amounts.view`-style permission — Stage 3 decides). Tabs registry `ProjectController::$tabs` (`overview`, `stages`, `payments`, `variations`, `attachments`, `handover`, `activity`); a tab shows only when its view exists.
- `ProjectsTable`: search number, titles, client name; filters status, service, manager, emirate, `late` boolean, contract value range, start date range (date column `start_date`), planned end range (custom filter); `contract_value` summable.
- Menu group `projects`: `projects.projects` (icon `building-skyscraper`). Search provider on number, titles, client name. Trash registry. Client tab `projects` and figure `projects` count (visible only).
- Notification `Assigned` to the manager and each new team member (not the actor).

- [ ] **Step 1: Failing tests** — ProjectsTest: `creates a project directly with number PRJ-2026-0001, VAT from settings and contract value equal to the original`, `ignores a posted contract_value`, `notifies the manager and new team members`, `computes days left and late`, `filters late projects and sums contract value`, `blocks deleting a client that has a project`, `shows the project page with its tabs`. ProjectScopeTest: `lists projects an engineer manages or belongs to only`, `returns 404 on another project page, its media and in search`, `keeps export and delete all inside visible projects`.
- [ ] **Step 2: Run** `php artisan test --filter="ProjectsTest|ProjectScopeTest"` — expected FAIL.
- [ ] **Step 3: Implement** enums, migrations, models, factory, scope, provider registrations.
- [ ] **Step 4: Implement** request, controller, table, search provider, views, routes, client tab.
- [ ] **Step 5: Run** the filter and the whole suite — expected PASS. Check list, form and page at 375 px and 1440 px in both languages.
- [ ] **Step 6: Commit** `stage-2: projects`.

---

### Task 3: Project stages, progress and timeline

**Files:**
- Create: `app/Enums/StageStatus.php`, `app/Models/ProjectStage.php` + migration, `app/Services/ProjectProgress.php`, `app/Http/Controllers/ProjectStageController.php`, `app/Http/Requests/SaveProjectStageRequest.php`, `resources/views/projects/tabs/stages.blade.php`, `resources/views/projects/partials/timeline.blade.php`
- Modify: `app/Http/Controllers/ProjectController.php` (copy the template on creation), `app/Providers/ProjectsServiceProvider.php` (due task)
- Test: `tests/Feature/Projects/ProjectStagesTest.php`, `tests/Unit/ProjectProgressTest.php`

**Interfaces:**
- `StageStatus`: `NotStarted`, `InProgress`, `Done` (values snake case).
- `project_stages`: `project_id`, `name_ar`, `name_en`, `sort_order`, `weight` decimal(5,2), `planned_start`, `planned_end`, `actual_start`, `actual_end` (dates, nullable), `status`, `progress` decimal(5,2) 0–100, `notes`, timestamps, `deleted_at`; `HasAttachments` (photos, permission prefix `projects`).
- `ProjectProgress::compute(Collection $stages): string` — Σ(weight × progress) / Σ(weight), 2 decimals; `'0.00'` when there are no stages or all weights are 0. `ProjectProgress::refresh(Project $p): void` stores it on the project; called after every stage save or delete.
- Setting a stage to Done sets progress 100 and `actual_end` today if empty; progress 100 sets Done; progress > 0 sets InProgress and `actual_start` if empty.
- `ProjectStage::isLate(): bool` — planned end before today and not Done.
- Stages tab: editable list (add, edit in modal, delete with soft delete, drag order), per stage a progress bar and late badge, attachments per stage; the timeline shows planned against actual bars per stage across the project dates (pure CSS, logical properties).
- Due task `stage-late` daily: for each late stage, `Notifier::toRoles(StageLate, $stage->project, …)` plus `toUser` to the manager, dedupe key `'stage-'.$stage->id`.

- [ ] **Step 1: Failing tests** — Unit: `weights normalised`, `no stages gives 0.00`, `zero weights give 0.00`. Feature: `copies the service stage template on creation`, `refreshes project progress after a stage update`, `done means 100 and the reverse`, `flags late stages and notifies once`, `an engineer outside the project cannot change its stages`.
- [ ] **Step 2: Run** `php artisan test --filter="ProjectStagesTest|ProjectProgressTest"` — expected FAIL.
- [ ] **Step 3: Implement** enum, model, migration, service, controller (permission `projects.update` + visibility), request, tab, timeline, due task, template copy on creation.
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-2: project stages and progress`.

---

### Task 4: Payment schedule (installments)

**Files:**
- Create: `app/Enums/{InstallmentStatus,InstallmentDueType}.php`, `app/Models/ProjectInstallment.php` + migration, `app/Services/InstallmentSchedule.php`, `app/Http/Controllers/ProjectInstallmentController.php`, `app/Http/Requests/SaveInstallmentRequest.php`, `resources/views/projects/tabs/payments.blade.php`
- Modify: `app/Providers/ProjectsServiceProvider.php`, `app/Services/ProjectProgress.php` (a stage reaching Done makes its installments due)
- Test: `tests/Feature/Projects/InstallmentsTest.php`

**Interfaces:**
- `InstallmentStatus`: `NotDue='not_due'`, `Due='due'`, `Invoiced='invoiced'`, `Collected='collected'` (the last two are set by Stage 3).
- `InstallmentDueType`: `Date='date'`, `Stage='stage'`.
- `project_installments`: `project_id`, `label_ar`, `label_en`, `percent` (nullable decimal 5,2), `amount` decimal(14,2), `due_type`, `due_date` (nullable), `stage_id` (nullable FK project_stages), `status`, `variation_id` (nullable, Task 6), `sort_order`, timestamps, `deleted_at`.
- `InstallmentSchedule::fromPercents(Project $p, array $lines): void` — amounts from the contract value with the last line taking the remainder (reuse `QuotationTotals::paymentAmounts` logic through a shared helper); `InstallmentSchedule::difference(Project $p): string` — contract value minus Σ amounts.
- An installment is Due when its date is today or past, or its stage is Done; the daily due task `installments-due` moves NotDue → Due and sends `InstallmentDue` (days before, rule days) to the configured roles, dedupe `'due-'.$installment->id`.
- Payments tab: rows with label, percent or amount (either may be entered; the other is derived), due rule, status badge; a warning panel when `difference ≠ 0` showing the gap (spec §8.8). The "create invoice" button is a Stage 3 slot.

- [ ] **Step 1: Failing tests** — `builds a schedule from percents with amounts that sum to the contract value`, `derives amount from percent and the reverse`, `warns when the schedule does not match the contract value`, `becomes due on its date and when its stage is done`, `notifies installment due once per installment`, `refuses changes to invoiced installments` (status invoiced set directly in the test).
- [ ] **Step 2: Run** `php artisan test --filter=InstallmentsTest` — expected FAIL.
- [ ] **Step 3: Implement** enums, model, migration, schedule service, controller, request, tab, due task.
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-2: payment schedule`.

---

### Task 5: Convert an accepted quotation to a project

**Files:**
- Create: `app/Actions/Projects/CreateProjectFromQuotation.php`, `resources/views/quotations/partials/convert-action.blade.php`
- Modify: `app/Http/Controllers/ProjectController.php` (create with `?quotation=`), `app/Providers/ProjectsServiceProvider.php` (register the button through `QuotationController::$primaryActionViews`), `resources/views/quotations/show.blade.php` (project link when converted)
- Test: `tests/Feature/Projects/ConvertQuotationTest.php`

**Interfaces:**
- `CreateProjectFromQuotation::handle(Quotation $q, array $data, User $by): Project` — only for an accepted quotation without a project; in one transaction with `lockForUpdate()` on the quotation row: project with the quotation's client, service, emirate, location, titles, `original_value = contract_value = quotation total`, `vat_rate = quotation vat_rate`; items copied to `project_items`; quotation payments copied as installments through `InstallmentSchedule::fromPercents()` (Task 4); stages copied from `StageTemplate::forService()` like a direct creation (Task 3). `$data` holds the fields the user confirms on the form: dates, manager, team, billing method.
- The project form opened from a quotation shows the copied values read-only and asks only for the confirm fields.
- Button "convert to project" on an accepted quotation (`projects.create` + quotation visible); once converted the quotation page links to the project instead.

- [ ] **Step 1: Failing tests** — `converts an accepted quotation with value, items, installments and the service stages`, `refuses a draft, sent or rejected quotation`, `creates one project when converted twice` (second call returns the existing project, still one row), `needs projects.create and quotation visibility`, `links the project from the quotation page`.
- [ ] **Step 2: Run** `php artisan test --filter=ConvertQuotationTest` — expected FAIL.
- [ ] **Step 3: Implement** the action (unique `quotation_id` index as the final guard; a unique violation returns the existing project), controller paths, button.
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-2: convert quotation to project`.

---

### Task 6: Variation orders

**Files:**
- Create: `app/Enums/VariationStatus.php`, `app/Models/{Variation,VariationItem}.php` + migrations, `app/Actions/Projects/{SaveVariation,DecideVariation}.php`, `app/Http/Controllers/VariationController.php`, `app/Http/Requests/SaveVariationRequest.php`, `resources/views/projects/tabs/variations.blade.php`, `resources/views/variations/{form,show}.blade.php`
- Modify: `app/Providers/ProjectsServiceProvider.php`, `lang/{ar,en}/projects.php`
- Test: `tests/Feature/Projects/VariationsTest.php`

**Interfaces:**
- `VariationStatus`: `Proposed`, `Approved`, `Rejected`.
- `variations`: `number` (VO, unique), `project_id`, `description` (text), `subtotal`, `vat_amount`, `total` decimal(14,2) — negative allowed for removed work, `status`, `approved_at`, `decided_by`, `rejection_note`, `created_by`, timestamps, `deleted_at`; `variation_items` like `project_items` with signed quantities. Client approval file through `HasAttachments` (category from lookups).
- Totals use the project's VAT rate; a line with a negative quantity removes work.
- `DecideVariation::approve(Variation $v, User $by): void` — only from Proposed, in a transaction with `lockForUpdate()` on the project: `variations_total += total`, `contract_value = original_value + variations_total`, adds an installment (label from the variation number, amount = total, due type date = today, `variation_id`); `reject()` only from Proposed. Approved and rejected variations are read-only.
- Permissions: `projects.update` to create, `projects.approve` (registered) to approve or reject.

- [ ] **Step 1: Failing tests** — `creates a variation with number VO-2026-0001 and signed totals`, `approving adds to the contract value once and adds an installment`, `approving twice or after rejection changes nothing`, `a removal variation lowers the contract value`, `the installment warning uses the value after variations`, `needs projects.approve to decide`.
- [ ] **Step 2: Run** `php artisan test --filter=VariationsTest` — expected FAIL.
- [ ] **Step 3: Implement** enum, models, migrations, actions, controller, request, views (item rows reuse the quotation item partial styles and `quotation-form.js` totals through a `data-allow-negative` flag).
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-2: variation orders`.

---

### Task 7: Handover, warranty and the handover record PDF

**Files:**
- Create: `app/Models/ProjectHandover.php` + migration, `app/Http/Controllers/ProjectHandoverController.php`, `app/Http/Requests/SaveHandoverRequest.php`, `resources/views/projects/tabs/handover.blade.php`, `resources/views/pdf/handover.blade.php`
- Modify: `app/Providers/ProjectsServiceProvider.php` (due tasks), `lang/{ar,en}/pdf.php`, `lang/{ar,en}/projects.php`
- Test: `tests/Feature/Projects/HandoverTest.php`

**Interfaces:**
- `project_handovers`: `project_id` (unique), `handover_date`, `warranty_months` (int), `warranty_end` (date, computed with `addMonthsNoOverflow`), `notes`, `created_by`, timestamps; signed record via `HasAttachments`.
- Saving a handover sets the project status Delivered and `actual_end_date` if empty; deleting it is not offered (correct it by editing).
- `projects.handover.pdf` (GET `?lang=ar|en|both`, `projects.print` + visibility): handover record with company header, client, project, date, scope summary (stages), warranty period and end, two signature boxes; logs `printed`.
- Due tasks daily: `project-ending` — projects in progress whose planned end is within the rule days → `ProjectEnding` to the manager, dedupe `'ending-'.planned_end_date`; `warranty-ending` — warranty end within the rule days → `WarrantyEnding` to the roles, dedupe `'warranty-'.warranty_end`.

- [ ] **Step 1: Failing tests** — `records a handover, delivers the project and computes warranty end`, `month-end safe warranty end` (2027-01-31 + 1 month = 2027-02-28), `renders the handover PDF in three languages`, `notifies project ending and warranty ending once`, `needs projects.update and visibility`.
- [ ] **Step 2: Run** `php artisan test --filter=HandoverTest` — expected FAIL.
- [ ] **Step 3: Implement** model, migration, controller, request, tab, PDF view, due tasks.
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS; open one handover PDF and check it.
- [ ] **Step 5: Commit** `stage-2: handover and warranty`.

---

### Task 8: Maintenance requests

**Files:**
- Create: `app/Enums/MaintenanceStatus.php`, `app/Models/MaintenanceRequest.php` + migration, `database/factories/MaintenanceRequestFactory.php`, `app/Http/Controllers/MaintenanceController.php`, `app/Http/Requests/SaveMaintenanceRequest.php`, `app/Livewire/Tables/MaintenanceTable.php`, `app/Search/MaintenanceSearchProvider.php`, `resources/views/maintenance/{index,form,show}.blade.php`, `lang/{ar,en}/maintenance.php`
- Modify: `app/Providers/ProjectsServiceProvider.php`, `resources/views/projects/tabs/handover.blade.php` (requests of the project)
- Test: `tests/Feature/Projects/MaintenanceTest.php`

**Interfaces:**
- `MaintenanceStatus`: `Open`, `InProgress`, `Done`, `Cancelled`.
- `maintenance_requests`: `number` (MR, unique), `project_id`, `request_date`, `description`, `in_warranty` (bool, computed on save from the project's warranty end and `request_date`; editable only by `maintenance.update` with a reason in notes), `status`, `assigned_to` (FK users), `cost` decimal(14,2) default 0, `notes`, `created_by`, timestamps, `deleted_at`; photos via `HasAttachments`.
- Scope: `HasRecordScope` on `assigned_to`, plus visible to the project's manager and team (custom scope like `Project`).
- `MaintenanceTable`: search number, description, project title; filters status, project, assignee, in warranty, date range on `request_date`; `cost` summable.
- Assigning notifies (`Assigned`). Menu `maintenance.maintenance` in group `projects` (icon `tool`). Search provider. Trash.

- [ ] **Step 1: Failing tests** — `creates a request with number MR-2026-0001 inside warranty`, `outside warranty after the warranty end`, `without a handover it is outside warranty`, `assigned user and project team see it, others get 404`, `filters by in warranty and sums cost`.
- [ ] **Step 2: Run** `php artisan test --filter=MaintenanceTest` — expected FAIL.
- [ ] **Step 3: Implement** enum, model, migration, factory, controller, request, table, search, views, provider.
- [ ] **Step 4: Run** the filter and the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-2: maintenance requests`.

---

### Task 9: Demo data and stage gate

**Files:**
- Create: `database/seeders/ProjectsDemoSeeder.php`
- Modify: `database/seeders/DemoSeeder.php`, `database/seeders/RolesSeeder.php` (starting permissions: management all of `projects` and `maintenance` incl. approve; engineer `projects.view`, `projects.update`, `maintenance.view|create|update`; accountant `projects.view`, `projects.print`), `docs/plans/PROGRESS.md`
- Test: `tests/Feature/Projects/ProjectsDemoSeederTest.php`

- [ ] **Step 1: Failing test** — seeder creates ≥ 12 projects (some from accepted quotations, all statuses, late ones, with stages, installments, variations, 3 delivered with warranty, 10 maintenance requests) and every projects page and the handover PDF answer 200 for super-admin; roles have their starting permissions.
- [ ] **Step 2: Run** the filter — expected FAIL. **Step 3: Implement.**
- [ ] **Step 4: Quality gates** — `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G && php artisan test && npm run build`; no new log errors.
- [ ] **Step 5: Visual pass** at 375 px and 1440 px, Arabic and English: projects list, project page (each tab), stage modal, variation form, maintenance list. No horizontal scroll.
- [ ] **Step 6: Final self-review** of the stage against the Review Focus; fix Critical / Important with a failing test first.
- [ ] **Step 7: Update `docs/plans/PROGRESS.md`**; commit `stage-2: demo data and stage gate`.
