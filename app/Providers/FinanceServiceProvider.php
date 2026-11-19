<?php

namespace App\Providers;

use App\Actions\Finance\CancelInvoice;
use App\Actions\Finance\IssueInvoice;
use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Search\ExpenseSearchProvider;
use App\Search\InvoiceSearchProvider;
use App\Search\ReceiptSearchProvider;
use App\Search\SearchRegistry;
use App\Services\Dashboard\DashboardRegistry;
use App\Services\Dashboard\Period;
use App\Services\DueTasks;
use App\Services\Finance\ProjectLedger;
use App\Services\InstallmentSchedule;
use App\Services\Notifier;
use App\Support\LookupUsage;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\ReportRegistry;
use App\Support\TrashRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 3 (finance): the one place where finance modules join the earlier registries.
 */
class FinanceServiceProvider extends ServiceProvider
{
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        // The search registry is a container singleton, so it is filled on every boot.
        $this->app->make(SearchRegistry::class)->register(new InvoiceSearchProvider);
        $this->app->make(SearchRegistry::class)->register(new ReceiptSearchProvider);
        $this->app->make(SearchRegistry::class)->register(new ExpenseSearchProvider);

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('invoices', [...PermissionRegistry::ACTIONS, 'cancel']);
        PermissionRegistry::register('receipts', ['view', 'create', 'update', 'export', 'print', 'cancel']);
        PermissionRegistry::register('expenses');
        PermissionRegistry::register('finance', ['costs', 'profitability']);
        PermissionRegistry::register('reports', ['view', 'export']);

        Menu::register('finance', ['label' => 'finance.invoices.invoices', 'icon' => 'file-invoice', 'route' => 'invoices.index', 'permission' => 'invoices.view']);
        Menu::register('finance', ['label' => 'finance.receipts.receipts', 'icon' => 'cash', 'route' => 'receipts.index', 'permission' => 'receipts.view']);
        Menu::register('finance', [
            'label' => 'finance.expenses.expenses',
            'icon' => 'receipt',
            'route' => 'expenses.index',
            'permission' => 'expenses.view',
            'active_routes' => ['expenses.index', 'expenses.create', 'expenses.show', 'expenses.edit'],
        ]);
        Menu::register('finance', [
            'label' => 'finance.expenses.quick',
            'icon' => 'camera',
            'route' => 'expenses.quick',
            'permission' => 'expenses.create',
            'active_routes' => 'expenses.quick',
        ]);
        Menu::register('finance', ['label' => 'reports.reports', 'icon' => 'chart-bar', 'route' => 'reports.index', 'permission' => 'reports.view']);
        TrashRegistry::register(Invoice::class);
        TrashRegistry::register(Expense::class);

        ReportRegistry::register('profitability', 'tables.reports.profitability-report', 'finance', ['finance.profitability']);
        ReportRegistry::register('aging', 'tables.reports.receivables-aging-report', 'finance', ['invoices.view']);
        ReportRegistry::register('collections', 'tables.reports.collections-report', 'finance', ['receipts.view']);
        ReportRegistry::register('expenses', 'tables.reports.expenses-report', 'finance', ['expenses.view', 'finance.costs']);
        ReportRegistry::register('vat', 'tables.reports.vat-summary-report', 'finance', ['invoices.view', 'expenses.view']);
        ReportRegistry::register('statement', 'tables.reports.client-statement-report', 'finance', ['invoices.view', 'receipts.view']);
        ReportRegistry::register('installments', 'tables.reports.installments-report', 'finance', ['invoices.view']);
        $this->registerDashboard();

        Project::$linkedRelations[] = 'invoices';
        ProjectController::$tabPermissions['invoices'] = ['invoices.view'];
        Client::$linkedRelations[] = 'invoices';
        Client::$linkedRelations[] = 'receipts';
        Project::$linkedRelations[] = 'receipts';
        Project::$linkedRelations[] = 'expenses';
        ProjectController::$tabPermissions['expenses'] = ['expenses.view', 'finance.costs'];
        LookupUsage::register(LookupType::ExpenseCategory, fn (Lookup $lookup) => Expense::withTrashed()->where('category_id', $lookup->id)->exists());
        LookupUsage::register(LookupType::PaymentMethod, fn (Lookup $lookup) => Expense::withTrashed()->where('payment_method_id', $lookup->id)->exists());

        // Cost = expenses of the project before VAT (spec §8.6); Stage 5 adds purchases and labour.
        ProjectLedger::$costSources['expenses'] = fn (Project $project) => (string) Expense::query()->where('project_id', $project->id)->sum('amount');
        InvoiceController::$showViews[] = 'invoices.partials.receipts';
        LookupUsage::register(LookupType::PaymentMethod, fn (Lookup $lookup) => Receipt::query()->where('payment_method_id', $lookup->id)->exists());

        // Collected = active receipts of the project, plus what project-less receipts pay on its invoices.
        ProjectLedger::$collectedSources['receipts'] = fn (Project $project) => bcadd(
            (string) Receipt::query()->active()->where('project_id', $project->id)->sum('amount'),
            (string) ReceiptAllocation::query()
                ->whereHas('receipt', fn ($receipts) => $receipts->active()->whereNull('project_id'))
                ->whereHas('invoice', fn ($invoices) => $invoices->where('project_id', $project->id))
                ->sum('amount'),
            2,
        );
        ClientController::$figureProviders['invoiced'] = fn (Client $client) => auth()->user()?->can('invoices.view')
            ? money((string) $client->invoices()->active()->visibleTo(auth()->user())->sum('total'))
            : '';
        // What the client still owes on issued invoices.
        ClientController::$figureProviders['outstanding'] = fn (Client $client) => auth()->user()?->can('invoices.view')
            ? money(bcsub(
                (string) $client->invoices()->active()->visibleTo(auth()->user())->sum('total'),
                (string) $client->invoices()->active()->visibleTo(auth()->user())->sum('paid_amount'),
                2,
            ))
            : '';
        InvoiceController::$actionViews[] = 'invoices.partials.share-actions';
        ClientController::$actionViews[] = 'reports.partials.client-statement-actions';

        // The installment follows its invoice (spec §8.8): issued → invoiced; cancelled → due again by its own rule.
        IssueInvoice::$afterIssue[] = function (Invoice $invoice) {
            if ($invoice->installment_id !== null) {
                ProjectInstallment::query()->whereKey($invoice->installment_id)->update(['status' => InstallmentStatus::Invoiced->value]);
            }
        };
        CancelInvoice::$afterCancel[] = function (Invoice $invoice) {
            if ($invoice->installment_id !== null) {
                ProjectInstallment::query()->whereKey($invoice->installment_id)->update(['status' => InstallmentStatus::NotDue->value]);
                InstallmentSchedule::markDue($invoice->project);
            }
        };

        self::registerDueTasks();
    }

    /** Periodic checks of the finance modules (no cron, spec §3.2, §5.5). */
    public static function registerDueTasks(): void
    {
        // Issued invoices past their due date: the configured roles, once per invoice.
        DueTasks::register('invoices-overdue', 1440, function () {
            $notifier = app(Notifier::class);

            Invoice::query()->overdue()->with('project')->whereHas('project')
                ->each(fn (Invoice $invoice) => $notifier->toRoles(
                    NotificationType::InvoiceOverdue, $invoice, ['number' => (string) $invoice->number],
                    route('invoices.show', $invoice), 'overdue-'.$invoice->id,
                ));
        });

        // Due installments past their date and still not invoiced: once per installment.
        DueTasks::register('installments-overdue', 1440, function () {
            $notifier = app(Notifier::class);

            ProjectInstallment::query()->with('project')
                ->where('status', InstallmentStatus::Due->value)
                ->where('due_type', InstallmentDueType::Date->value)
                ->whereDate('due_date', '<', today())
                ->whereHas('project')
                ->each(fn (ProjectInstallment $installment) => $notifier->toRoles(
                    NotificationType::InstallmentOverdue, $installment->project, ['number' => $installment->project->number],
                    route('projects.show', [$installment->project, 'tab' => 'payments']), 'overdue-'.$installment->id,
                ));
        });
    }

    /** Money figures on the dashboard (spec §9.1). */
    private function registerDashboard(): void
    {
        $inPeriod = fn (Builder $query, string $column, Period $period) => $query
            ->whereDate($query->qualifyColumn($column), '>=', $period->from->toDateString())
            ->whereDate($query->qualifyColumn($column), '<=', $period->to->toDateString());
        $open = fn (Builder $query) => bcsub((string) (clone $query)->sum('total'), (string) (clone $query)->sum('paid_amount'), 2);

        DashboardRegistry::register('collected', 'receipts.view',
            fn (User $user, Period $period) => (string) $inPeriod(Receipt::query()->visibleTo($user)->active(), 'receipt_date', $period)->sum('amount'),
            fn (User $user, Period $period) => route('receipts.index', ['f' => ['status' => ReceiptStatus::Active->value], ...$period->query()]),
            'finance', 'money');
        DashboardRegistry::register('outstanding', 'invoices.view',
            fn (User $user) => $open(Invoice::query()->visibleTo($user)->active()),
            fn (User $user) => ReportRegistry::allows($user, 'aging') ? route('reports.show', 'aging') : route('invoices.index'),
            'finance', 'money');
        DashboardRegistry::register('overdue_collection', 'invoices.view',
            fn (User $user) => $open(Invoice::query()->visibleTo($user)->overdue()),
            fn () => route('invoices.index', ['f' => ['overdue' => '1']]),
            'finance', 'money');
        DashboardRegistry::register('overdue_invoices', 'invoices.view',
            fn (User $user) => Invoice::query()->visibleTo($user)->overdue()->count(),
            fn () => route('invoices.index', ['f' => ['overdue' => '1']]),
            'finance');
        DashboardRegistry::register('due_installments', 'invoices.view',
            fn (User $user) => ProjectInstallment::query()
                ->whereIn('project_id', Project::query()->visibleTo($user)->select('projects.id'))
                ->where('status', InstallmentStatus::Due->value)
                ->count(),
            fn (User $user) => ReportRegistry::allows($user, 'installments') ? route('reports.show', ['installments', 'f' => ['status' => InstallmentStatus::Due->value]]) : null,
            'finance');
        DashboardRegistry::register('period_expenses', ['expenses.view', 'finance.costs'],
            fn (User $user, Period $period) => (string) $inPeriod(Expense::query()->visibleTo($user), 'expense_date', $period)->sum('total'),
            fn (User $user, Period $period) => route('expenses.index', $period->query()),
            'finance', 'money');
        // Invoiced before VAT minus expenses before VAT in the period, as in the VAT summary.
        DashboardRegistry::register('period_profit', 'finance.profitability',
            fn (User $user, Period $period) => bcsub(
                (string) $inPeriod(Invoice::query()->visibleTo($user)->active(), 'issue_date', $period)->sum('subtotal'),
                (string) $inPeriod(Expense::query()->visibleTo($user), 'expense_date', $period)->sum('amount'),
                2,
            ),
            fn (User $user, Period $period) => ReportRegistry::allows($user, 'vat') ? route('reports.show', ['vat', ...$period->query()]) : null,
            'finance', 'money');
    }
}
