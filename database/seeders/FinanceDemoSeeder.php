<?php

namespace Database\Seeders;

use App\Actions\Finance\AllocateReceipt;
use App\Actions\Finance\CancelInvoice;
use App\Actions\Finance\CancelReceipt;
use App\Actions\Finance\CreateInvoiceFromInstallment;
use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\SaveExpense;
use App\Actions\Finance\SaveInvoice;
use App\Actions\Finance\SaveReceipt;
use App\Enums\InstallmentStatus;
use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Models\User;
use DomainException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Local finance demo data (Stage 3): invoices in every status (some overdue), receipts with
 * partial, full and advance allocations, a cancelled invoice with its replacement, a cancelled
 * receipt, an invoice from an installment, and 40 expenses over categories, some general.
 * Everything goes through the finance actions, so totals, numbers and ledgers are real.
 * Run after ProjectsDemoSeeder, or through DemoSeeder.
 */
class FinanceDemoSeeder extends Seeder
{
    /** [issued days ago, days until due, amount before VAT, outcome] in date order. */
    private const INVOICES = [
        [150, 30, '42000', 'paid'],
        [135, 30, '11000', 'overdue'],
        [120, 30, '18500', 'paid'],
        [95, 30, '26000', 'partial'],
        [75, 15, '12400', 'overdue'],
        [50, 14, '9800', 'overdue'],
        [40, 30, '31000', 'partial'],
        [25, 30, '15750', 'issued'],
        [20, 30, '7200', 'replaced'],
        [12, 30, '22300', 'issued'],
        [6, 30, '5400', 'paid'],
        [3, 30, '14100', 'draft'],
        [1, 30, '8600', 'draft'],
    ];

    private const ITEMS = [
        ['أعمال العزل المائي للسطح', 'Roof waterproofing works'],
        ['توريد وتركيب بلاط الأرضيات', 'Supply and installation of floor tiles'],
        ['أعمال الدهانات الداخلية', 'Interior painting works'],
        ['أعمال الجبس والأسقف المعلقة', 'Gypsum and false ceiling works'],
        ['أعمال الخرسانة للأساسات', 'Foundation concrete works'],
        ['دفعة حسب نسبة الإنجاز', 'Progress payment'],
    ];

    private const SUPPLIERS = [
        'مؤسسة الخليج لمواد البناء', 'شركة الإمارات للدهانات', 'مصنع الشارقة للعزل', 'مؤسسة الريم للمعدات',
        'محطة وقود أدنوك', 'شركة النقل السريع', null,
    ];

    private const NOTES = [
        'مواد عزل للسطح', 'إيجار رافعة ليوم واحد', 'وقود سيارات الموقع', 'أجور عمال إضافيين',
        'دهانات ومواد تشطيب', 'نقل مخلفات الموقع', 'أدوات ومستلزمات', 'رسوم تصاريح البلدية',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FinanceDemoSeeder is for local use only.');
        }

        mt_srand(2028);

        $admin = User::role(['management', 'super-admin'])->first() ?? User::query()->firstOrFail();
        $accountant = User::role('accountant')->first() ?? $admin;
        $engineers = User::role('engineer')->get();
        $engineers = $engineers->isEmpty() ? collect([$admin]) : $engineers;
        $methods = Lookup::query()->ofType(LookupType::PaymentMethod)->pluck('id');
        $categories = Lookup::query()->ofType(LookupType::ExpenseCategory)->pluck('id');
        $projects = Project::query()
            ->whereNotIn('status', [ProjectStatus::NotStarted->value, ProjectStatus::Cancelled->value])
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty() || $methods->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $this->invoicesAndReceipts($projects, $admin, $accountant, $methods);
        $this->installmentInvoice($admin);
        $this->expenses($projects, $categories, $methods, $engineers, $accountant);
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, int>  $methods
     */
    private function invoicesAndReceipts(Collection $projects, User $admin, User $accountant, Collection $methods): void
    {
        $save = app(SaveInvoice::class);
        $issue = app(IssueInvoice::class);
        $allocate = app(AllocateReceipt::class);

        foreach (self::INVOICES as $i => [$daysAgo, $dueDays, $amount, $outcome]) {
            $project = $projects[$i % $projects->count()];
            $issued = today()->subDays($daysAgo);
            [$ar, $en] = self::ITEMS[$i % count(self::ITEMS)];

            $invoice = $save->handle([
                'project_id' => $project->id,
                'issue_date' => $issued->toDateString(),
                'due_date' => $issued->copy()->addDays($dueDays)->toDateString(),
                'items' => [['description_ar' => $ar, 'description_en' => $en, 'quantity' => '1', 'unit_price' => $amount]],
            ], $admin);

            if ($outcome === 'draft') {
                continue;
            }

            $invoice = $issue->handle($invoice, $admin);

            match ($outcome) {
                'paid' => $this->receive($invoice, (string) $invoice->total, $issued->copy()->addDays(12), $accountant, $methods, $allocate),
                'partial' => $this->receive($invoice, bcmul((string) $invoice->total, '0.4', 2), $issued->copy()->addDays(20), $accountant, $methods, $allocate),
                'replaced' => $this->replace($invoice, $admin, $issue),
                default => null,
            };
        }

        $this->advanceAndCancelled($projects, $accountant, $methods, $allocate);
    }

    /**
     * @param  Collection<int, int>  $methods
     */
    private function receive(Invoice $invoice, string $amount, Carbon $date, User $accountant, Collection $methods, AllocateReceipt $allocate): void
    {
        $receipt = app(SaveReceipt::class)->handle([
            'client_id' => $invoice->client_id,
            'project_id' => $invoice->project_id,
            'receipt_date' => $date->min(today())->toDateString(),
            'amount' => $amount,
            'payment_method_id' => $methods[mt_rand(0, $methods->count() - 1)],
            'reference' => 'TRX-'.mt_rand(100000, 999999),
            'received_by' => $accountant->id,
        ], $accountant);

        $allocate->handle($receipt, [$invoice->id => $amount], $accountant);
    }

    /** Cancelled for a wrong amount; the replacement is issued at once. */
    private function replace(Invoice $invoice, User $admin, IssueInvoice $issue): void
    {
        $replacement = app(CancelInvoice::class)->handle($invoice, 'خطأ في قيمة البند', $admin, true);

        if ($replacement !== null) {
            $issue->handle($replacement, $admin);
        }
    }

    /**
     * An advance with nothing allocated, a payment larger than its invoice, and a cancelled receipt.
     *
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, int>  $methods
     */
    private function advanceAndCancelled(Collection $projects, User $accountant, Collection $methods, AllocateReceipt $allocate): void
    {
        $save = app(SaveReceipt::class);
        $receipt = fn (Project $project, string $amount, int $daysAgo) => $save->handle([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'receipt_date' => today()->subDays($daysAgo)->toDateString(),
            'amount' => $amount,
            'payment_method_id' => $methods->first(),
            'received_by' => $accountant->id,
        ], $accountant);

        $receipt($projects->last(), '10000.00', 8);

        $open = Invoice::query()->active()->whereColumn('paid_amount', '<', 'total')->whereDate('due_date', '>=', today())->first();
        if ($open !== null) {
            $over = $receipt($open->project, bcadd(bcsub((string) $open->total, (string) $open->paid_amount, 2), '2500', 2), 2);
            $allocate->handle($over, [$open->id => bcsub((string) $open->total, (string) $open->paid_amount, 2)], $accountant);
        }

        $cancelled = $receipt($projects->first(), '3000.00', 30);
        app(CancelReceipt::class)->handle($cancelled, 'شيك مرتجع', $accountant);
    }

    /** One invoice made from a due installment, issued. */
    private function installmentInvoice(User $admin): void
    {
        $installment = ProjectInstallment::query()->where('status', InstallmentStatus::Due->value)->orderBy('id')->first();

        if ($installment === null) {
            return;
        }

        try {
            $invoice = app(CreateInvoiceFromInstallment::class)->handle($installment, $admin);
            app(IssueInvoice::class)->handle($invoice, $admin);
        } catch (DomainException) {
            // Not due after all; the demo goes on without it.
        }
    }

    /**
     * 40 expenses over six months; every fifth one is general (no project).
     *
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, int>  $categories
     * @param  Collection<int, int>  $methods
     * @param  Collection<int, User>  $engineers
     */
    private function expenses(Collection $projects, Collection $categories, Collection $methods, Collection $engineers, User $accountant): void
    {
        $save = app(SaveExpense::class);
        $modes = ['included', 'excluded', 'none'];

        for ($i = 0; $i < 40; $i++) {
            $general = $i % 5 === 0;
            $payer = $general ? $accountant : $engineers[$i % $engineers->count()];

            $save->handle([
                'project_id' => $general ? null : $projects[$i % $projects->count()]->id,
                'category_id' => $categories[$i % $categories->count()],
                'supplier_name' => self::SUPPLIERS[$i % count(self::SUPPLIERS)],
                'expense_date' => today()->subDays((int) round($i * 4.3))->toDateString(),
                'payment_method_id' => $methods[$i % $methods->count()],
                'paid_by' => $payer->id,
                'supplier_invoice_no' => $i % 3 === 0 ? null : 'SI-'.mt_rand(1000, 9999),
                'description' => self::NOTES[$i % count(self::NOTES)],
                'amount' => (string) (mt_rand(35, 950) * 10),
                'vat_mode' => $modes[$i % count($modes)],
            ], $payer);
        }
    }
}
