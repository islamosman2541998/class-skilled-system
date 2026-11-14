<?php

namespace App\Providers;

use App\Actions\Finance\CancelInvoice;
use App\Actions\Finance\IssueInvoice;
use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Search\InvoiceSearchProvider;
use App\Search\SearchRegistry;
use App\Services\DueTasks;
use App\Services\InstallmentSchedule;
use App\Services\Notifier;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\TrashRegistry;
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

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('invoices', [...PermissionRegistry::ACTIONS, 'cancel']);
        PermissionRegistry::register('finance', ['costs', 'profitability']);

        Menu::register('finance', ['label' => 'finance.invoices.invoices', 'icon' => 'file-invoice', 'route' => 'invoices.index', 'permission' => 'invoices.view']);
        TrashRegistry::register(Invoice::class);

        Project::$linkedRelations[] = 'invoices';
        ProjectController::$tabPermissions['invoices'] = ['invoices.view'];
        Client::$linkedRelations[] = 'invoices';
        ClientController::$figureProviders['invoiced'] = fn (Client $client) => auth()->user()?->can('invoices.view')
            ? money((string) $client->invoices()->active()->visibleTo(auth()->user())->sum('total'))
            : '';
        InvoiceController::$actionViews[] = 'invoices.partials.share-actions';

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
}
