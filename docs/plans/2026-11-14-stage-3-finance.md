# Stage 3 — Finance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tax invoices (from a due installment in one click or directly), receipts allocated to invoices or kept as advances, expenses with a phone quick entry, project profitability, role dashboards and the finance and sales reports.

**Architecture:** Same module pattern as Stages 1–2 (model, Form Requests, thin controller, `DataTable`, Blade, feature tests). Money rules live in `app/Actions/Finance` and `app/Services/Finance`; one `FinanceServiceProvider` joins the registries (filled once per process). Every money change ends in `ProjectLedger::refresh($project)`, which rewrites the summary columns Stage 2 left at zero (`invoiced_total`, `collected_total`, `cost_total`) plus a new `invoiced_net`; lists and the project page read those columns, never sums (spec §11). Report tables are `DataTable`s over `fromSub()` aggregates, so search, filters, totals and export keep working.

**Tech Stack:** As Stages 0–2 (mPDF for invoice, receipt and statement PDFs). No new package; the dashboard chart is server-rendered inline SVG.

**Spec:** `docs/specs/2026-11-12-class-skilled-system-design.md` §8.8 (invoice from installment), §8.10, §8.11, §8.12, §8.6 (summary figures, tabs), §9.1, §9.2, §5.5 (InstallmentOverdue, InvoiceOverdue), §5.9, §5.10, §6 (cancel financial document, see amounts and costs, see profitability), §7 (finance settings, document templates).

## Global Constraints

- Everything in `CLAUDE.md` → Global Constraints applies unchanged. No new composer or npm package.
- Money `decimal(14,2)`, quantities `decimal(12,3)`, rates `decimal(5,2)`; totals on the server with `bcmath` and `QuotationCalculator::round` (half up). The VAT rate is copied into each invoice and expense when created (§5.9).
- Numbers from `NumberGenerator`: `Invoice` (INV) reserved **at issue**, not at draft; `Receipt` (RV) and `Expense` (EXP) at creation (§5.9).
- An issued invoice is never edited; it is cancelled with a reason and may be replaced. A receipt is cancelled with a reason, never deleted. Expenses and draft invoices soft delete.
- Every new lang group goes into `TranslationCatalog::GROUPS`. Replies to the user in Arabic.
- Commit at the end of every task with the message shown. Local commits only.
- Out of this stage: progress claims and invoices from claims (Stage 4 adds `claim_id`); suppliers, purchases, subcontracts, labour and equipment costs and the supplier statement (Stage 5 adds `supplier_id` and new cost sources to `ProjectLedger`).

## Business rules this plan fixes (from the spec and Stage 2 data)

- Contract value and installments **include VAT** (they come from the quotation total). An invoice made from an installment has `total = installment amount` exactly; `subtotal = round(total × 100 / (100 + vat_rate))`, `vat = total − subtotal`.
- Invoice status: `draft → issued → partially_paid → paid`, or `cancelled`. Paid states are computed from active receipt allocations (§8.10). Overdue = issued or partially paid with `due_date < today` (not a stored status).
- Installment status follows its invoice: issue → `invoiced`; invoice paid → `collected`; invoice cancelled → back to `due`/`not_due` by its own rule (`InstallmentSchedule::markDue`).
- Project figures: `invoiced_total` = sum of `total` of issued, partially paid and paid invoices; `invoiced_net` = the same over `subtotal`; `collected_total` = sum of active receipts of the project (advances included); `cost_total` = sum of expense `amount` (before VAT) of the project; outstanding = invoiced − collected (already `Project::outstanding()`); profit = `invoiced_net − cost_total`; margin = profit ÷ `invoiced_net` (empty when zero).
- Special permissions (§6): `invoices.cancel`, `receipts.cancel`, `finance.costs` (cost figure, expenses tab, cost columns), `finance.profitability` (profit and margin figures, profitability report). Quotation amounts stay visible to sales.

## Review Focus

1. Two receipts allocated to the same invoice at the same moment, or one receipt allocated twice, never collect more than the invoice total; allocations lock the invoice row and check the remaining amount inside the transaction. [Task 3]
2. Issuing the same draft twice (double click, two tabs) reserves one number and leaves one issued invoice; one installment never has two active invoices. [Tasks 1, 2]
3. The invoice total from an installment equals the installment amount when the VAT split rounds (10,000.01 at 5%; 0.01). [Task 2]
4. Cancelling an invoice with active allocations is refused with a message; cancelling a receipt restores the invoice and installment states and the project figures. [Tasks 1, 3]
5. Period edges in reports: an invoice issued on the last day of the period is included, drafts and cancelled invoices are excluded from VAT and aging, a receipt cancelled after the period no longer counts. [Tasks 6, 7]

---

### Task 1: Invoices core

**Files:**
- Create: `app/Providers/FinanceServiceProvider.php` (in `bootstrap/providers.php`), `app/Enums/InvoiceStatus.php`, `app/Models/{Invoice,InvoiceItem}.php` + migration (also `projects.invoiced_net`), factories, `app/Services/Finance/{InvoiceCalculator,ProjectLedger}.php`, `app/Actions/Finance/{SaveInvoice,IssueInvoice,CancelInvoice}.php`, `app/Http/Controllers/InvoiceController.php`, `app/Http/Requests/{SaveInvoiceRequest,CancelDocumentRequest}.php`, `app/Livewire/Tables/InvoicesTable.php`, `app/Tables/Filters/DateRangeFilter.php`, `app/Search/InvoiceSearchProvider.php`, `resources/views/invoices/{index,form,show}.blade.php` + partials, `resources/views/projects/tabs/invoices.blade.php`, `resources/views/clients/tabs/invoices.blade.php`, `lang/{ar,en}/finance.php`
- Modify: `app/Support/TranslationCatalog.php`, `lang/{ar,en}/{permissions,menu}.php`
- Test: `tests/Feature/Finance/InvoicesTest.php`, `tests/Unit/InvoiceCalculatorTest.php`

**Interfaces:**
- Produces: `InvoiceStatus` (`Draft`, `Issued`, `PartiallyPaid`, `Paid`, `Cancelled`; `label()`, `color()`, `isActive()` = issued or partially paid or paid).
- `invoices`: `number` nullable unique, `project_id`, `client_id`, `installment_id` nullable, `replaces_id` nullable (self), `issue_date`, `due_date`, `status`, `vat_rate`, `subtotal`, `vat_amount`, `total`, `paid_amount` default 0, `notes`, `terms_ar`, `terms_en`, `issued_at`, `issued_by`, `cancel_reason`, `cancelled_at`, `cancelled_by`, `created_by`, timestamps, `deleted_at`. `invoice_items`: `description_ar`, `description_en`, `unit_id`, `quantity`, `unit_price`, `line_total`, `sort_order`.
- `InvoiceCalculator::fromLines(array $lines, string $vatRate): InvoiceTotals` and `InvoiceCalculator::fromGross(string $total, string $vatRate): InvoiceTotals` (`subtotal`, `vat`, `total`, `lines`).
- `ProjectLedger::refresh(Project $project): void` and `public static array $costSources` (closures `fn (Project) => string`, expenses register in Task 4, Stage 5 adds more).
- `IssueInvoice::handle(Invoice, User): Invoice` (locks the row, returns it unchanged when already issued). `CancelInvoice::handle(Invoice, string $reason, User, bool $replace = false): ?Invoice` (returns the new draft when replacing; throws `DomainException` with a translated message when active allocations exist).
- Scope: `Invoice::scopeVisibleTo` = the project is visible (`Project::visibleTo`).
- Permissions: `invoices` = ACTIONS + `cancel`; module `finance` with `costs`, `profitability`. Menu group `finance` (label `menu.finance`), item `finance.invoices` icon `file-invoice`.

- [ ] **Step 1: Failing tests** — unit: `splits a gross amount so subtotal plus vat equals it` (10,000.01 / 5% → 9,523.82 + 476.19; 0.01 → 0.01 + 0.00), `totals lines with half-up rounding`. Feature: `creates a draft without a number`, `issues with INV-2026-0001 and locks editing (403 on edit, update refused)`, `issuing twice keeps one number`, `cancels with a reason and frees nothing else`, `refuses cancelling with active allocations` (allocation row inserted directly), `replace cancels and opens a draft copy with replaces_id`, `deletes drafts only`, `issued invoices raise project invoiced_total and invoiced_net`, `engineer sees invoices of own projects only (list, page, search)`, `filters overdue and due date range`.
- [ ] **Step 2: Run** `php artisan test --filter="InvoicesTest|InvoiceCalculatorTest"` — expected FAIL.
- [ ] **Step 3: Implement.** Draft form: project (visible), client taken from the project, dates (due = issue + `FinanceSettings::invoice_due_days`), item rows (reuse the variation item-row partial pattern, positive quantities only), terms from `TemplateSettings` (`invoice_terms_ar/en`, new settings migration with `invoice_footer_*`, `whatsapp_invoice_*`, `whatsapp_receipt_*`). Show page: status, actions by state (edit/delete/issue for drafts; cancel / replace for active, behind `invoices.cancel`). `InvoicesTable` columns number, client, project, issue date, due date (red when overdue), total (summable), paid, remaining, status; search number, client name, project number and title; filters status, overdue (boolean), project, client, total range, due date range (`DateRangeFilter`); date column `issue_date`. Drafts join `TrashRegistry`. Project tab `invoices` (label "الفواتير والمقبوضات") lists the project invoices, Task 3 adds receipts below. Client page tab and figure "invoiced".
- [ ] **Step 4: Run** the filter, then the whole suite — expected PASS.
- [ ] **Step 5: Commit** `stage-3: invoices`.

### Task 2: Invoice from installment, PDF, WhatsApp, overdue notices

**Files:**
- Create: `app/Actions/Finance/CreateInvoiceFromInstallment.php`, `app/Http/Controllers/InvoicePdfController.php`, `resources/views/pdf/invoice.blade.php`, `resources/views/projects/partials/installment-invoice-action.blade.php`
- Modify: `app/Support/WhatsApp.php`, `app/Http/Controllers/PublicDocumentController.php`, `routes/web.php` (`/d/i/{invoice}` signed), `resources/views/projects/tabs/payments.blade.php`, `app/Providers/FinanceServiceProvider.php`, `app/Actions/Finance/{IssueInvoice,CancelInvoice}.php`
- Test: `tests/Feature/Finance/InvoiceFromInstallmentTest.php`, `tests/Feature/Finance/InvoicePdfTest.php`

**Interfaces:**
- Consumes: `InvoiceCalculator::fromGross`, `IssueInvoice`, `CancelInvoice` (Task 1); `InstallmentSchedule::markDue` (Stage 2).
- Produces: `CreateInvoiceFromInstallment::handle(ProjectInstallment, User): Invoice` — returns the existing active or draft invoice of that installment instead of a second one; one line labelled with the installment label, gross = installment amount, project VAT rate.
- Due tasks: `invoices-overdue` (InvoiceOverdue to roles, key `overdue-{invoice id}`), `installments-overdue` (InstallmentOverdue to roles for due installments past their date, key `overdue-{installment id}`).

- [ ] **Step 1: Failing tests** — `creates a draft invoice from a due installment with total equal to the amount`, `second click opens the same invoice`, `issuing marks the installment invoiced; cancelling returns it to due`, `refuses an installment that is not due` (422 with message), `renders the tax invoice PDF in ar, en and both with company and client TRN`, `signed WhatsApp link opens the PDF and an altered link is 403`, `notifies overdue invoices and installments once`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** "Create invoice" button on due installment rows (`invoices.create`). PDF layout follows `pdf/quotation`: title "فاتورة ضريبية / Tax invoice", company TRN and bank details from `CompanySettings`, client TRN, VAT summary block, footer and stamp. WhatsApp message from `whatsapp_invoice_*` with amount and due date.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-3: invoice from installment and invoice PDF`.

### Task 3: Receipts and allocations

**Files:**
- Create: `app/Enums/ReceiptStatus.php` (`Active`, `Cancelled`), `app/Models/{Receipt,ReceiptAllocation}.php` + migration, factories, `app/Services/Finance/InvoiceBalance.php`, `app/Actions/Finance/{SaveReceipt,AllocateReceipt,CancelReceipt}.php`, `app/Http/Controllers/{ReceiptController,ReceiptPdfController}.php`, `app/Http/Requests/{SaveReceiptRequest,AllocateReceiptRequest}.php`, `app/Livewire/Tables/ReceiptsTable.php`, `app/Search/ReceiptSearchProvider.php`, `resources/views/receipts/{index,form,show}.blade.php` + partials, `resources/views/pdf/receipt.blade.php`
- Modify: `resources/views/projects/tabs/invoices.blade.php`, `resources/views/invoices/show.blade.php` (payments list, "record receipt"), client page tab, `app/Providers/FinanceServiceProvider.php`
- Test: `tests/Feature/Finance/ReceiptsTest.php`

**Interfaces:**
- `receipts`: `number`, `client_id`, `project_id` nullable, `receipt_date`, `amount`, `payment_method_id` (lookup `payment_method`), `reference`, `cheque_date`, `received_by` (user), `notes`, `status`, `cancel_reason`, `cancelled_at`, `cancelled_by`, `created_by`, timestamps, `deleted_at`; attachment via `HasAttachments`. `receipt_allocations`: `receipt_id`, `invoice_id`, `amount`, timestamps; unique (`receipt_id`, `invoice_id`).
- `InvoiceBalance::refresh(Invoice): void` — `paid_amount` = active allocations; status issued / partially paid / paid; installment `collected` when paid, back to `invoiced` otherwise; then `ProjectLedger::refresh`.
- `AllocateReceipt::handle(Receipt, array<int,string> $amountsByInvoiceId, User): void` — replaces the receipt allocations; each invoice locked, active, same client (and same project when the receipt has one); each amount ≤ invoice remaining + this receipt's current share; sum ≤ receipt amount. Unallocated rest = advance.
- `Receipt::unallocated(): string`. Scope: project visible, or for receipts without a project the client visible.
- Permissions `receipts` = `view`, `create`, `update`, `export`, `print`, `cancel`. Menu `finance.receipts` icon `cash`.

- [ ] **Step 1: Failing tests** — `records a receipt RV-2026-0001 allocated to one invoice and marks it partially paid`, `allocating the rest marks it paid and the installment collected`, `splits one receipt over two invoices`, `keeps an advance and settles it later`, `refuses more than the invoice remaining or the receipt amount`, `refuses another client's invoice`, `cancelling restores invoice, installment and project collected_total`, `cannot delete a receipt`, `renders the receipt PDF`, `filters by method, received by and amount`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Receipt form: client (visible), project (optional, of that client), date, amount, method, reference, cheque date (shown for the cheque method code), received by (default current user), notes, then an allocation grid of the client's open invoices with "remaining" and an amount box each, plus "unallocated". "Record receipt" on an invoice opens the form prefilled. Project tab shows receipts under invoices; project figure "collected" from the ledger.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-3: receipts and allocations`.

### Task 4: Expenses and quick entry

**Files:**
- Create: `app/Models/Expense.php` + migration, factory, `app/Http/Controllers/ExpenseController.php`, `app/Http/Requests/{SaveExpenseRequest,QuickExpenseRequest}.php`, `app/Livewire/Tables/ExpensesTable.php`, `app/Search/ExpenseSearchProvider.php`, `resources/views/expenses/{index,form,show,quick}.blade.php` + partials, `resources/views/projects/tabs/expenses.blade.php`
- Modify: `app/Providers/FinanceServiceProvider.php` (cost source, lookup usage for `expense_category` and `payment_method`)
- Test: `tests/Feature/Finance/ExpensesTest.php`

**Interfaces:**
- `expenses`: `number`, `project_id` nullable (general expense), `category_id` (lookup `expense_category`), `supplier_name` nullable (Stage 5 adds `supplier_id`), `expense_date`, `amount` (before VAT), `vat_rate`, `vat_amount`, `total`, `payment_method_id`, `paid_by` (user), `supplier_invoice_no`, `description`, `created_by`, timestamps, `deleted_at`; receipt photo via `HasAttachments` (permission `expenses`).
- VAT: form takes `amount` and a "includes VAT" switch with the rate from `FinanceSettings`; server computes the three figures. Cost source registered: `fn (Project $p) => sum(amount)`.
- Scope: users of an "assigned only" role see expenses of their projects and those they created. General expenses (no project) need a "see all" role.
- Permissions `expenses` = ACTIONS. Menu `finance.expenses` icon `receipt`. The project tab and cost columns require `finance.costs`.

- [ ] **Step 1: Failing tests** — `records EXP-2026-0001 on a project and raises cost_total by the amount before VAT`, `computes VAT from an amount that includes it`, `general expense has no project and is hidden from engineers`, `engineer adds a quick expense with a photo to own project only`, `deleting an expense lowers cost_total; restoring raises it`, `filters by category, project, supplier (search), entered by, paid by, method and amount`, `hides the expenses tab and cost figure without finance.costs`.
- [ ] **Step 2: Run** the filter — expected FAIL.
- [ ] **Step 3: Implement.** Quick entry page `/expenses/quick` (phone first): photo (uploader, camera capture), amount, category, project (own projects), optional note; saves and returns to the same page with a toast. Project page cost figure and expenses tab behind `finance.costs`. Model events on save, delete and restore call `ProjectLedger::refresh` for the old and new project.
- [ ] **Step 4: Run** the filter and the suite — expected PASS.
- [ ] **Step 5: Commit** `stage-3: expenses`.

### Task 5: Project profitability and money visibility

**Files:**
- Modify: `resources/views/projects/show.blade.php` (figures), `app/Livewire/Tables/ProjectsTable.php` (invoiced, collected, cost, profit, margin columns hidden by default and behind permissions), `app/Http/Controllers/ClientController.php` figure providers via `FinanceServiceProvider` (invoiced, outstanding)
- Test: `tests/Feature/Finance/ProfitabilityTest.php`

**Interfaces:**
- Consumes: `ProjectLedger` columns. Produces: `Project::profit(): string`, `Project::margin(): ?string` (two decimals, `null` when `invoiced_net` is 0).

- [ ] **Step 1: Failing tests** — `profit is invoiced net minus cost and margin its share`, `margin is empty without invoices`, `profit and margin hidden without finance.profitability, cost hidden without finance.costs (page and table export)`, `client page shows invoiced and outstanding`.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** the filter and the suite — PASS.
- [ ] **Step 5: Commit** `stage-3: project profitability`.

### Task 6: Finance reports

**Files:**
- Create: `app/Http/Controllers/ReportController.php`, `resources/views/reports/{index,show}.blade.php`, `app/Livewire/Tables/Reports/{ProfitabilityReport,ReceivablesAgingReport,CollectionsReport,ExpensesReport,VatSummaryReport}.php`, `app/Models/ReportRow.php` (read-only model over `fromSub`), `lang/{ar,en}/reports.php`
- Modify: `app/Providers/FinanceServiceProvider.php` (menu `reports.reports` icon `chart-bar`, permission module `reports` = `view`, `export`)
- Test: `tests/Feature/Finance/FinanceReportsTest.php`

**Interfaces:**
- Each report table extends `DataTable`, `query()` returns `ReportRow::query()->fromSub(<aggregate>, 'r')`, so search, filters, sort, totals and export work unchanged. The date range applies inside the aggregate.
- Profitability: per project — contract, invoiced, collected, cost by category (one column per active expense category), profit, margin (requires `finance.profitability`). Aging: per client — total outstanding and buckets current, 1–30, 31–60, 61–90, over 90 by days past `due_date` at the "as of" date (default today). Collections: receipts by period (day / month switch) and payment method. Expenses: by category and project. VAT summary: output VAT from issued invoices by `issue_date`, input VAT from expenses by `expense_date`, net.
- Scope: reports use the same visibility as their sources.

- [ ] **Step 1: Failing tests** — one per report with exact sums from fixed fixtures, including the Review Focus edges (invoice on the last day of the range counted, draft and cancelled excluded, cancelled receipt excluded), `aging puts an invoice 45 days overdue in 31–60`, `profitability report needs finance.profitability`, `each report exports`.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** the filter and the suite — PASS.
- [ ] **Step 5: Commit** `stage-3: finance reports`.

### Task 7: Sales and project reports, client statement

**Files:**
- Create: `app/Livewire/Tables/Reports/{SalesPerformanceReport,LateWorkReport,ClientStatementReport}.php`, `app/Http/Controllers/ClientStatementController.php`, `resources/views/pdf/client-statement.blade.php`
- Test: `tests/Feature/Finance/SalesReportsTest.php`, `tests/Feature/Finance/ClientStatementTest.php`

**Interfaces:**
- Sales performance: leads by source and by service, quotations by status and by assignee, conversion rate = accepted ÷ (sent + accepted + rejected) in the range. Late work: late projects and late stages in one list with type, project, manager, days late. Client statement: opening balance before the range, then issued invoices (debit) and active receipts (credit) by date with running balance; PDF in ar / en / both from the client page.

- [ ] **Step 1: Failing tests** — `conversion rate counts only decided and sent quotations in the range`, `late work lists a stage past its planned end and a running project past its end date`, `statement running balance and opening balance are exact`, `statement PDF renders in three languages`.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** the filter and the suite — PASS.
- [ ] **Step 5: Commit** `stage-3: sales reports and client statement`.

### Task 8: Role dashboards

**Files:**
- Create: `app/Services/Dashboard/{DashboardRegistry,Widgets}.php`, `resources/views/home/{dashboard,widgets/*}.blade.php`, `resources/views/components/bar-chart.blade.php` (inline SVG)
- Modify: `app/Http/Controllers/HomeController.php`, `resources/views/home.blade.php`
- Test: `tests/Feature/Finance/DashboardTest.php`

**Interfaces:**
- `DashboardRegistry::register(string $key, string $permission, Closure $figure, Closure $url)` filled by the providers; a figure shows when the user has its permission, so the dashboard follows the role without role names in code. Period filter (`?from=&to=`, default this month) passed to every figure.
- Figures (§9.1): management — running projects, late projects, contracts total, collected, outstanding, overdue collection, period profit, new leads, quotation conversion, revenue vs cost by month (last 6 months); sales — my leads, follow-ups today and overdue, quotations waiting, quotations expiring; engineer — my projects, visits today, late stages ("reports not sent" arrives with daily reports in Stage 4); accountant — due installments, overdue invoices, receipts and expenses of the period. Each figure links to its list with the matching `f[...]`, `from`, `to` query.
- Notifications and own activity from Stage 0 stay below the figures.

- [ ] **Step 1: Failing tests** — `management sees money figures with exact values for the period`, `engineer sees only own projects counts and no money`, `every figure link opens its list and the list total matches the figure` (overdue invoices, due installments, my leads), `chart has six months`.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** the filter and the suite — PASS.
- [ ] **Step 5: Commit** `stage-3: role dashboards`.

### Task 9: Demo data and stage gate

**Files:**
- Create: `database/seeders/FinanceDemoSeeder.php`
- Modify: `database/seeders/{DemoSeeder,RolesSeeder}.php` (management all `invoices`, `receipts`, `expenses`, `reports`, `finance.*`; accountant the same except `force-delete`; engineer `expenses.view`, `expenses.create`, `finance.costs` off; sales `reports.view`), `docs/plans/PROGRESS.md`
- Test: `tests/Feature/Finance/FinanceDemoSeederTest.php`

- [ ] **Step 1: Failing test** — seeder creates invoices in every status (some overdue), receipts with partial, full and advance allocations, one cancelled invoice with a replacement, 40 expenses over categories and general ones; every finance list, report, dashboard and PDF answers 200 for super-admin; roles have their starting permissions.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.**
- [ ] **Step 4: Quality gates** — `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G && php artisan test && npm run build`; no new log errors.
- [ ] **Step 5: Visual pass** at 375 px and 1440 px, Arabic and English: invoices list / form / page, receipt form with allocations, quick expense on the phone, reports, dashboards per role, invoice and receipt PDFs. No horizontal scroll, check the browser console.
- [ ] **Step 6: Final self-review** against the Review Focus; fix Critical / Important with a failing test first.
- [ ] **Step 7: Update `docs/plans/PROGRESS.md`**; commit `stage-3: demo data and stage gate`.
