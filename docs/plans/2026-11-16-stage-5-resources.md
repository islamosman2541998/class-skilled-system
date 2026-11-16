# Stage 5 — Resources Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Suppliers and subcontractors with their purchases, subcontracts and payment vouchers (allocated over their invoices, with a supplier statement and PDF), daily labour attendance and equipment assignments, all charged to the project cost, plus the retention release invoice at handover for projects billed by claims.

**Architecture:** Same module pattern as Stages 1–4 (model, Form Requests, thin controller, `DataTable`, Blade, feature tests). A new `ResourcesServiceProvider` (in `bootstrap/providers.php`, after `SiteServiceProvider`) joins the registries: permissions, menu (group `resources`, already in `lang/*/menu.php`), project tabs `purchases` and `resources`, search, trash, reports, and `ProjectLedger::$costSources` (`purchases`, `labour`, `equipment`). Purchase invoices mirror Stage 3 invoices (lines through `InvoiceCalculator::fromLines`); payment vouchers mirror receipts (`AllocateReceipt` / `CancelReceipt` locking pattern); the supplier statement mirrors `ClientStatement`. Cost rows store their computed cost when saved, so the ledger and reports sum stored columns (spec §11).

**Tech Stack:** As Stages 0–4 (mPDF for the supplier statement PDF, the Stage 0 uploader for the purchase invoice attachment). No new package.

**Spec:** `docs/specs/2026-11-12-class-skilled-system-design.md` §8.15 (suppliers), §8.16 (purchases, subcontracts, payment vouchers), §8.17 (labour and equipment), §9.2 (supplier statement with PDF, profitability cost by type, VAT summary), §5.9 (numbers, VAT), §5.2 (financial documents are cancelled, not deleted), §6 (permissions and record scope), §11 (stored totals).

## Global Constraints

- Everything in `CLAUDE.md` → Global Constraints applies unchanged. No new composer or npm package.
- Money `decimal(14,2)`, quantities `decimal(12,3)`, rates `decimal(5,2)`, hours `decimal(5,2)`; totals on the server with `bcmath` and `QuotationCalculator::round` (half up).
- Numbers from `NumberGenerator`: purchase invoices `DocumentType::Purchase` (prefix `PB`), payment vouchers `DocumentType::PaymentVoucher` (prefix `PV`), subcontracts a new `DocumentType::Subcontract` (prefix `SC`, added to the numbering settings and `DocumentSequenceSeeder`).
- A payment voucher is never deleted: it is cancelled with a reason and stays visible (§5.2). A purchase invoice soft deletes only while no active voucher pays it.
- New lang group `resources` goes into `TranslationCatalog::GROUPS`. Replies to the user in Arabic.
- Commit at the end of every task with the message shown. Local commits only.
- Out of this stage: stock / warehouse (materials go straight to project cost, §8.16), salaries (§8.17), payment voucher PDF (not in §5.10), backup and deployment (Stage 6).

## Business rules this plan fixes

- A supplier has a type `supplier` or `subcontractor`; suppliers are not scoped by project (all users with `suppliers.view` see them). Purchases, subcontracts, attendance and equipment assignments are scoped by project visibility like invoices.
- Purchase invoice: supplier, project (required), date, the supplier's invoice number (unique per supplier among live invoices), lines (description, unit, quantity, unit price), VAT rate either the settings rate or 0 (non-registered supplier), subtotal / VAT / total from `InvoiceCalculator::fromLines`, notes, attachment. Project cost counts the subtotal (before VAT); the VAT summary counts its VAT as input VAT by invoice date.
- Payment status of a purchase invoice comes from active voucher allocations: `unpaid`, `partial`, `paid` (stored `paid_amount` and `status`, refreshed under row locks like `InvoiceBalance`).
- Subcontract: subcontractor (type `subcontractor`), project, scope of work, value before VAT, VAT rate (settings or 0), start / end dates, status `active` / `completed` / `cancelled`, payment lines (label, amount before VAT, due date). "Bill" on a payment line creates a purchase invoice for that line (one per line, a second click returns it) with `subcontract_id` and `subcontract_payment_id`. Billed lines are read-only. The subcontract's billed total never exceeds its value.
- Payment voucher: supplier, date, amount, payment method, reference, notes, allocations over that supplier's live purchase invoices (never more than an invoice's remaining or the voucher's amount; rows locked). Cancelling frees its allocations.
- Supplier page: figures purchases total, paid, due (Σ remaining), projects count; tabs purchases, subcontracts, payment vouchers, projects, attachments, activity.
- Supplier statement: opening balance before the period, purchase invoices as credit (owed to the supplier), active vouchers as debit, running balance; empty until a supplier is chosen; PDF in Arabic / English / both.
- Workers: name ar/en, trade (lookup `worker_trade`), daily wage, `is_company` (company or external), active. Attendance: one row per worker per day (a worker is on one project a day), status `present` / `half` / `absent`, overtime hours 0–12. Cost = wage × (1, 0.5 or 0) + overtime hours × wage ÷ `FinanceSettings::labour_hours_per_day` (default 8) × `FinanceSettings::overtime_multiplier` (default `1.25`), rounded half up, stored with the wage of that day. Entered for a group of workers on one screen per project and date; a blank status removes the worker's row for that day.
- Equipment: name ar/en, type (lookup `equipment_type`), `owned` / `rented`, daily cost, active. Assignment: equipment, project, from, to (both required, to ≥ from), daily cost copied (editable), days = to − from + 1, cost = days × daily cost, stored; overlapping assignments of the same equipment are refused.
- Project cost (`cost_total`) = expenses (Stage 3) + purchases (subtotals) + labour + equipment. Profitability shows cost by type: materials (purchases without subcontract), subcontracts (purchases with subcontract), labour, equipment, expenses; the per-category expense columns stay hidden by default.
- Retention release (deferred from Stage 4): on a project billed by claims with a handover, "retention invoice" creates one draft invoice for Σ `retention_amount` of approved and invoiced claims (before VAT, project VAT rate), `invoices.retention_release = true`; a second click returns the live one; refused when nothing is retained or there is no handover.
- Permissions: `suppliers`, `purchases`, `subcontracts`, `workers`, `equipment` = ACTIONS; `payment_vouchers` = `view`, `create`, `update`, `export`, `print`, `cancel`; `attendance` = `view`, `create`, `update`, `delete`, `export`.

## Review Focus

1. Two vouchers paying the same purchase invoice at the same moment never pay more than its total; cancelling a voucher puts the invoice back to its true status. [Task 4]
2. Billing the same subcontract payment line twice (double click) makes one purchase invoice, and billed lines never exceed the subcontract value. [Task 3]
3. Saving attendance twice for the same worker and day (two engineers, a double tap, or the same worker on two projects) leaves one row; changing a worker's wage later does not change past cost. [Task 6]
4. A purchase invoice with an active voucher cannot be deleted; its supplier and project cannot change once paid. [Tasks 2, 4]
5. The project cost and profitability move with every purchase, voucher-free edit, attendance save and equipment assignment, and the VAT summary input VAT includes purchases. [Tasks 2, 6, 7]

---

### Task 1: Suppliers and subcontractors

**Files:**
- Create: `app/Providers/ResourcesServiceProvider.php` (in `bootstrap/providers.php`), `app/Enums/SupplierType.php`, `app/Models/Supplier.php` + migration (also adds `DocumentType::Subcontract` sequence data and lookup type `supplier_specialty`), factory, `app/Http/Controllers/SupplierController.php`, `app/Http/Requests/SaveSupplierRequest.php`, `app/Livewire/Tables/SuppliersTable.php`, `app/Search/SupplierSearchProvider.php`, `resources/views/suppliers/{index,form,show}.blade.php`, `resources/views/suppliers/tabs/overview.blade.php`, `resources/svg/tabler/truck.svg`, `lang/{ar,en}/resources.php`
- Modify: `app/Enums/{LookupType,DocumentType}.php`, `database/seeders/{LookupSeeder,DocumentSequenceSeeder}.php` (supplier specialties, worker trades, equipment types; `SC` sequence), `app/Support/TranslationCatalog.php`, `lang/{ar,en}/{permissions,settings}.php`
- Test: `tests/Feature/Resources/SuppliersTest.php`

**Interfaces:**
- `suppliers`: `type`, `name_ar`, `name_en` nullable, `specialty_id` (lookup `supplier_specialty`) nullable, `phone`, `email`, `trn`, `address`, `bank_name`, `bank_account_name`, `bank_iban`, `notes`, `is_active` (default true), `created_by`, timestamps, `deleted_at`. `Supplier::getNameAttribute()` like `Client`. `Supplier` implements `GuardsDeletion` (blocked while it has purchases, subcontracts or vouchers; later tasks add the relations to `Supplier::$linkedRelations`).
- `SupplierController::$tabs` (tab ⇒ permission list) and `$figureSources` (key ⇒ `Closure(Supplier): string`) are filled by later tasks, like `ProjectController::$tabPermissions`.
- Menu group `resources`, item `resources.suppliers.suppliers` icon `truck`, permission `suppliers.view`.

- [ ] **Step 1: Failing tests** — `creates a supplier and a subcontractor with Arabic name only and shows English fallback`, `validates TRN 15 digits and IBAN format when given`, `filters by type, specialty and active, and searches name, phone and TRN`, `soft deletes a supplier without records and restores it from the trash`, `needs suppliers permissions (403)`.
- [ ] **Step 2: Run** `php artisan test --filter=SuppliersTest` — expected FAIL.
- [ ] **Step 3: Implement.** Form in two panels (identity and contact, bank details); show page with header figures (filled by Tasks 2–4, empty until then) and tabs; table columns name, type badge, specialty, phone, active; filters type, specialty, active.
- [ ] **Step 4: Run** the filter, then the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: suppliers`.

### Task 2: Purchase invoices

**Files:**
- Create: `app/Enums/PurchaseStatus.php` (`Unpaid`, `Partial`, `Paid`; `label()`, `color()`), `app/Models/{Purchase,PurchaseItem}.php` + migration, factory, `app/Actions/Resources/SavePurchase.php`, `app/Services/Resources/PurchaseBalance.php`, `app/Http/Controllers/PurchaseController.php`, `app/Http/Requests/SavePurchaseRequest.php`, `app/Livewire/Tables/PurchasesTable.php`, `app/Search/PurchaseSearchProvider.php`, `resources/views/purchases/{index,form,show}.blade.php`, `resources/views/projects/tabs/purchases.blade.php`, `resources/views/suppliers/tabs/purchases.blade.php`, `resources/svg/tabler/receipt.svg`
- Modify: `app/Livewire/Tables/Reports/VatSummaryReport.php` (purchases input VAT), `app/Providers/ResourcesServiceProvider.php`, `app/Models/{Project,Supplier}.php`
- Test: `tests/Feature/Resources/PurchasesTest.php`

**Interfaces:**
- `purchases`: `number` unique, `supplier_id`, `project_id`, `subcontract_id` nullable, `subcontract_payment_id` nullable unique, `purchase_date`, `supplier_invoice_no`, `vat_rate`, `subtotal`, `vat_amount`, `total`, `paid_amount` (default 0), `status`, `notes`, `created_by`, timestamps, `deleted_at`; index (`supplier_id`, `supplier_invoice_no`). `purchase_items`: `purchase_id`, `description_ar`, `description_en`, `unit_id`, `quantity`, `unit_price`, `line_total`, `sort_order`. Attachments through `HasAttachments` (permission `purchases`).
- `SavePurchase::handle(array $data, User $by, ?Purchase $purchase = null): Purchase` — lines and totals via `InvoiceCalculator::fromLines($lines, $vatRate, $decimals)`; `vat_rate` is the settings rate when `vat` is true, else `0`; refuses changing supplier or project once `paid_amount > 0`; refreshes `ProjectLedger` for the old and new project.
- `PurchaseBalance::refresh(Purchase $purchase): void` — `paid_amount` = Σ active allocations, status from it (used by Task 4).
- `Purchase::deletionBlockedReason()` — blocked while `paid_amount > 0`.
- `ProjectLedger::$costSources['purchases']` = Σ live purchase subtotals of the project.
- Project tab `purchases` (permission `purchases.view`); supplier tab `purchases`; supplier figure `purchases_total`.

- [ ] **Step 1: Failing tests** — `records a purchase invoice with two lines and VAT and the project cost rises by the subtotal`, `a non-registered supplier invoice has no VAT`, `refuses the same supplier invoice number twice for one supplier`, `editing amounts updates the project cost; deleting removes it`, `VAT summary input VAT includes purchases of the month`, `engineer sees purchases of own projects only`, `stores the supplier invoice attachment`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Form like the invoice form (supplier, project, date, supplier invoice no, VAT switch, lines, notes, uploader); show page with lines, totals, payment status and allocations panel (filled in Task 4); table columns number, supplier, project, supplier invoice no, date, total (summable), paid, status; filters supplier, project, status, date range.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: purchase invoices`.

### Task 3: Subcontracts

**Files:**
- Create: `app/Enums/SubcontractStatus.php`, `app/Models/{Subcontract,SubcontractPayment}.php` + migration, factory, `app/Actions/Resources/{SaveSubcontract,BillSubcontractPayment}.php`, `app/Http/Controllers/SubcontractController.php`, `app/Http/Requests/SaveSubcontractRequest.php`, `app/Livewire/Tables/SubcontractsTable.php`, `resources/views/subcontracts/{index,form,show}.blade.php`, `resources/views/suppliers/tabs/subcontracts.blade.php`, `resources/svg/tabler/file-text.svg`
- Modify: `resources/views/projects/tabs/purchases.blade.php` (subcontracts panel), `app/Providers/ResourcesServiceProvider.php`
- Test: `tests/Feature/Resources/SubcontractsTest.php`

**Interfaces:**
- `subcontracts`: `number` unique, `supplier_id`, `project_id`, `scope` (text), `value` (before VAT), `vat_rate`, `start_date`, `end_date` nullable, `status`, `notes`, `created_by`, timestamps, `deleted_at`. `subcontract_payments`: `subcontract_id`, `label_ar`, `label_en`, `amount`, `due_date` nullable, `sort_order`.
- `SaveSubcontract::handle(array $data, User $by, ?Subcontract $subcontract = null): Subcontract` — the supplier must be a subcontractor; Σ payment lines ≤ value; billed lines keep their amount (an edit that changes or removes one is refused).
- `BillSubcontractPayment::handle(SubcontractPayment $payment, User $by): Purchase` — locks the payment; returns its live purchase when one exists; creates a purchase through `SavePurchase` with one line (the payment label), the subcontract VAT rate, `subcontract_id`, `subcontract_payment_id`; refused on a cancelled subcontract.
- `Subcontract::billed(): string` (Σ live purchases), `remaining()`.

- [ ] **Step 1: Failing tests** — `creates a subcontract with three payment lines within its value`, `refuses payment lines above the value and a supplier that is not a subcontractor`, `billing a line creates one purchase invoice and a second click returns it`, `a billed line cannot be changed or removed`, `subcontract bills count as project cost and show in the subcontracts profitability column` (column added in Task 7, assert the purchase row here), `cancelled subcontract cannot be billed`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Form with scope, value, VAT switch, dates and payment lines rows; show page with lines (bill button or the purchase link and its payment status), billed / remaining figures; table columns number, subcontractor, project, value, billed, status; filters status, project, subcontractor.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: subcontracts`.

### Task 4: Payment vouchers

**Files:**
- Create: `app/Enums/PaymentVoucherStatus.php` (`Active`, `Cancelled`), `app/Models/{PaymentVoucher,PaymentAllocation}.php` + migration, factory, `app/Actions/Resources/{SavePaymentVoucher,AllocatePaymentVoucher,CancelPaymentVoucher}.php`, `app/Http/Controllers/PaymentVoucherController.php`, `app/Http/Requests/SavePaymentVoucherRequest.php`, `app/Livewire/Tables/PaymentVouchersTable.php`, `app/Search/PaymentVoucherSearchProvider.php`, `resources/views/payment-vouchers/{index,form,show}.blade.php`, `resources/views/suppliers/tabs/{payment_vouchers,projects}.blade.php`, `resources/svg/tabler/cash-banknote.svg`
- Modify: `resources/views/purchases/show.blade.php` (allocations panel), `app/Providers/ResourcesServiceProvider.php` (menu group `finance`, supplier figures `paid`, `due`, `projects`)
- Test: `tests/Feature/Resources/PaymentVouchersTest.php`

**Interfaces:**
- `payment_vouchers`: `number` unique, `supplier_id`, `voucher_date`, `amount`, `payment_method_id`, `reference`, `notes`, `status`, `cancel_reason`, `cancelled_at`, `cancelled_by`, `created_by`, timestamps (no soft delete). `payment_allocations`: `payment_voucher_id`, `purchase_id`, `amount`; unique pair.
- `SavePaymentVoucher::handle(array $data, User $by, ?PaymentVoucher $voucher = null): PaymentVoucher` — supplier fixed once created; amount never below what is allocated.
- `AllocatePaymentVoucher::handle(PaymentVoucher $voucher, array $amounts, User $by): void` — purchase id ⇒ amount; locks the voucher and every purchase in id order; each amount ≤ the purchase remaining (plus what this voucher already pays it), Σ ≤ voucher amount, purchases of the same supplier only; `ValidationException` on `allocations`; refreshes `PurchaseBalance`.
- `CancelPaymentVoucher::handle(PaymentVoucher $voucher, string $reason, User $by): void` — idempotent; refreshes the purchases it paid.
- Visibility: a voucher is visible to users who see all records, or when every purchase it pays is on a visible project.

- [ ] **Step 1: Failing tests** — `pays two purchase invoices partly and fully and their statuses follow`, `refuses more than an invoice remaining or the voucher amount`, `two vouchers on the same invoice never pay more than its total` (second allocation after the first fills it is refused), `cancelling a voucher returns the invoices to unpaid or partial and keeps the voucher visible as cancelled`, `a paid purchase cannot be deleted and its supplier cannot change`, `supplier page shows purchases, paid, due and projects`, `refuses allocating to another supplier's invoice`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Voucher form with the supplier's open purchases listed after the supplier is chosen (amount per invoice, "pay remaining" per row), like the receipt allocation screen; show page with allocations and cancel modal (reason); table columns number, supplier, date, amount (summable), allocated, method, status; filters supplier, method, status, date range.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: payment vouchers`.

### Task 5: Supplier statement

**Files:**
- Create: `app/Services/Resources/SupplierStatement.php`, `app/Livewire/Tables/Reports/SupplierStatementReport.php`, `app/Http/Controllers/SupplierStatementController.php`, `resources/views/pdf/supplier-statement.blade.php`
- Modify: `app/Providers/ResourcesServiceProvider.php` (`ReportRegistry::register('supplier-statement', …, 'finance', ['purchases.view', 'payment_vouchers.view'])`), `resources/views/suppliers/show.blade.php` (statement link and PDF button), `routes/resources.php`
- Test: `tests/Feature/Resources/SupplierStatementTest.php`

**Interfaces:**
- `SupplierStatement::query(?int $supplierId, User $user, ?Carbon $from, ?Carbon $to): QueryBuilder` and `openingBalance(int $supplierId, User $user, Carbon $from): string` — the shape of `ClientStatement` (entry, entry_date, number, debit, credit, balance) with purchases as credit and active vouchers as debit; running balance cast to `DECIMAL(15,2)` (MySQL float lesson from Stage 3).

- [ ] **Step 1: Failing tests** — `statement lists opening balance, purchases and vouchers in date order with the running balance` (two purchases, one voucher, one cancelled voucher not counted), `empty until a supplier is chosen`, `renders the supplier statement PDF in ar, en and both`, `needs purchases.view and payment_vouchers.view`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement** the report, the PDF (company header, supplier, period, lines, closing balance) and the buttons.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: supplier statement`.

### Task 6: Workers and attendance

**Files:**
- Create: `app/Enums/AttendanceStatus.php` (`Present`, `Half`, `Absent`; `factor()` 1 / 0.5 / 0), `app/Models/{Worker,Attendance}.php` + migration, factories, settings migration (`finance.labour_hours_per_day` 8, `finance.overtime_multiplier` `1.25`), `app/Services/Resources/LabourCost.php`, `app/Actions/Resources/SaveAttendance.php`, `app/Http/Controllers/{WorkerController,AttendanceController}.php`, `app/Http/Requests/{SaveWorkerRequest,SaveAttendanceRequest}.php`, `app/Livewire/Tables/{WorkersTable,AttendanceTable}.php`, `resources/views/workers/{index,form}.blade.php`, `resources/views/attendance/{index,sheet}.blade.php`, `resources/views/projects/tabs/resources.blade.php`, `resources/svg/tabler/{users-group,calendar-check}.svg`
- Modify: `app/Settings/FinanceSettings.php` + finance settings form and request, `app/Providers/ResourcesServiceProvider.php`
- Test: `tests/Feature/Resources/AttendanceTest.php`, `tests/Unit/LabourCostTest.php`

**Interfaces:**
- `workers`: `name_ar`, `name_en`, `trade_id` (lookup `worker_trade`), `daily_wage`, `is_company` (bool), `phone`, `is_active`, `created_by`, timestamps, `deleted_at`. `attendances`: `project_id`, `worker_id`, `work_date`, `status`, `overtime_hours` decimal(5,2) default 0, `daily_wage` (copied), `cost`, `created_by`, timestamps; unique (`worker_id`, `work_date`).
- `LabourCost::compute(string $wage, AttendanceStatus $status, string $overtimeHours, int $hoursPerDay, string $multiplier): string`.
- `SaveAttendance::handle(Project $project, Carbon $date, array $rows, User $by): void` — rows worker id ⇒ `{status, overtime_hours}`; locks the project; a worker already recorded on another project that day is refused (`ValidationException` on `rows.{worker}` naming that project); blank status deletes that worker's row for this project and day; keeps the stored wage of an existing row unless the status or hours change; refreshes `ProjectLedger`.
- `ProjectLedger::$costSources['labour']` = Σ attendance cost of the project. Menu items workers (`users-group`) and attendance (`calendar-check`) in group `resources`.

- [ ] **Step 1: Failing tests** — unit: `present costs the daily wage`, `half day costs half`, `overtime 2 hours at 200 wage, 8 hours, 1.25 costs 62.50`, `absent with overtime counts only the overtime`. Feature: `engineer records attendance for five workers on own project and the project cost rises`, `saving the same sheet twice keeps one row per worker`, `refuses a worker already recorded on another project that day`, `blank status removes the row`, `a later wage change does not change past cost`, `engineer cannot record on another engineer's project`, `attendance list filters by project, worker, trade and date`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Phone-first attendance sheet (§4.4): project and date at the top, every active worker as a row with status buttons (present / half / absent / none) and an overtime box, totals of workers and cost at the bottom, save button fixed at the bottom; workers list and form; attendance list (date, project, worker, trade, status, overtime, cost summable). Project tab `resources` (permission `attendance.view`) shows the labour cost by day and a link to the sheet.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: workers and attendance`.

### Task 7: Equipment and project cost by type

**Files:**
- Create: `app/Enums/EquipmentOwnership.php`, `app/Models/{Equipment,EquipmentAssignment}.php` + migration, factories, `app/Actions/Resources/SaveEquipmentAssignment.php`, `app/Http/Controllers/{EquipmentController,EquipmentAssignmentController}.php`, `app/Http/Requests/{SaveEquipmentRequest,SaveEquipmentAssignmentRequest}.php`, `app/Livewire/Tables/{EquipmentTable,EquipmentAssignmentsTable}.php`, `resources/views/equipment/{index,form,show}.blade.php`, `resources/svg/tabler/bulldozer.svg`
- Modify: `resources/views/projects/tabs/resources.blade.php` (equipment panel when `equipment.view`), `app/Livewire/Tables/Reports/ProfitabilityReport.php` (cost by type), `app/Providers/ResourcesServiceProvider.php`
- Test: `tests/Feature/Resources/EquipmentTest.php`, `tests/Feature/Resources/ProjectCostTest.php`

**Interfaces:**
- `equipment`: `name_ar`, `name_en`, `type_id` (lookup `equipment_type`), `ownership`, `daily_cost`, `notes`, `is_active`, `created_by`, timestamps, `deleted_at`. `equipment_assignments`: `equipment_id`, `project_id`, `date_from`, `date_to`, `daily_cost`, `days`, `cost`, `notes`, `created_by`, timestamps, `deleted_at`.
- `SaveEquipmentAssignment::handle(array $data, User $by, ?EquipmentAssignment $assignment = null): EquipmentAssignment` — locks the equipment row; refuses overlap with another live assignment of the same equipment (`ValidationException` on `date_from` naming the project and dates); computes days and cost; refreshes `ProjectLedger` (old and new project).
- `ProjectLedger::$costSources['equipment']` = Σ live assignment cost.
- Profitability columns `cost_materials`, `cost_subcontracts`, `cost_labour`, `cost_equipment`, `cost_expenses` (summable, shown), then the existing hidden category columns, `cost_total`, profit, margin.

- [ ] **Step 1: Failing tests** — `assigns rented equipment for 10 days and the project cost rises by 10 × daily cost`, `refuses an overlapping assignment of the same equipment`, `deleting an assignment removes its cost`, `project cost total is expenses + purchases + labour + equipment`, `profitability shows materials, subcontracts, labour, equipment and expenses columns that add up to the cost`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Equipment list, form and page (assignments history); assignment modal on the equipment page and on the project resources tab; profitability columns.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: equipment and cost by type`.

### Task 8: Retention release at handover

**Files:**
- Create: `app/Actions/Site/CreateRetentionInvoice.php`, migration (`invoices.retention_release` bool default false)
- Modify: `resources/views/projects/tabs/claims.blade.php` (button and retained / released figures), `routes/site.php`, `app/Http/Controllers/ClaimController.php` (or a small `RetentionInvoiceController`), `app/Models/Invoice.php`, `resources/views/invoices/show.blade.php` (label)
- Test: `tests/Feature/Site/RetentionInvoiceTest.php`

**Interfaces:**
- `CreateRetentionInvoice::handle(Project $project, User $by): Invoice` — locks the project; needs claims billing and a handover; amount = Σ `retention_amount` of approved and invoiced claims; returns the live retention invoice when one exists; draft invoice with one line "محتجز الضمان للمشروع :number" / "Retention release for project :number", VAT at the project rate; `DomainException` (translated) when nothing is retained or no handover.

- [ ] **Step 1: Failing tests** — `creates one retention invoice after handover for the retained amount`, `a second click returns the same invoice`, `refused before handover and when nothing is retained`, `needs invoices.create`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement** the action, route and button.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-5: retention release`.

### Task 9: Demo data and stage gate

**Files:**
- Create: `database/seeders/ResourcesDemoSeeder.php`
- Modify: `database/seeders/{DemoSeeder,RolesSeeder}.php` (management all new modules; accountant suppliers, purchases, subcontracts, payment vouchers full, workers / attendance / equipment view and export; engineer suppliers.view, purchases.view and create, attendance view / create / update, workers.view, equipment.view), `docs/plans/PROGRESS.md`
- Test: `tests/Feature/Resources/ResourcesDemoSeederTest.php`

- [ ] **Step 1: Failing test** — the seeder makes 8 suppliers and 3 subcontractors, purchases on running projects (some paid, some partly paid, some unpaid), two subcontracts with billed and unbilled lines, vouchers including one cancelled, 12 workers with 20 working days of attendance on running projects, 5 pieces of equipment with assignments, and a retention invoice on the delivered claims project when one exists; every list, record page, supplier statement PDF and the profitability report answer 200 for super-admin; roles have their starting permissions.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement** through the Stage 5 actions (no direct inserts).
- [ ] **Step 4: Quality gates** — `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G && php artisan test && npm run build`; `migrate:fresh --seed` and `DemoSeeder` on MySQL only after the user agrees (it resets local data).
- [ ] **Step 5: Visual pass** at 375 px and 1440 px, Arabic and English (needs a signed-in browser session): attendance sheet on the phone, purchase form, voucher allocation, supplier page and statement, subcontract page, equipment page, project purchases and resources tabs.
- [ ] **Step 6: Final self-review** against the Review Focus; fix Critical / Important with a failing test first.
- [ ] **Step 7: Update `docs/plans/PROGRESS.md`**; commit `stage-5: demo data and stage gate`.
