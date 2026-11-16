<?php

namespace Database\Seeders;

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\SaveInvoice;
use App\Actions\Projects\CreateProjectFromQuotation;
use App\Actions\Quotations\ChangeQuotationStatus;
use App\Actions\Quotations\SaveQuotation;
use App\Actions\Site\ApproveClaim;
use App\Actions\Site\SaveClaim;
use App\Actions\Site\SaveDailyReport;
use App\Actions\Site\StartClaim;
use App\Actions\Site\SubmitClaim;
use App\Enums\BillingMethod;
use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Enums\StageStatus;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use App\Services\Site\ReportCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Local site demo data (Stage 4): daily reports for the running projects over the last 20
 * working days with gaps and obstacles, and a project billed by claims from a quotation with a
 * discount and an advance whose claims are invoiced, approved and submitted, plus a draft claim
 * on a second claims project. Everything goes through the Stage 4 actions.
 * Run after ProjectsDemoSeeder, or through DemoSeeder.
 */
class SiteDemoSeeder extends Seeder
{
    private const WORK = [
        ['صب خرسانة الأعمدة في الدور الأرضي', 'نقص في حديد التسليح، تم طلب توريد جديد'],
        ['تركيب بلاط الأرضيات في الغرف الخلفية', null],
        ['أعمال العزل المائي للسطح وتجربة الماء', null],
        ['تمديدات الكهرباء في المجلس والصالة', 'تأخر وصول الكيابل من المورد'],
        ['لياسة الجدران الداخلية للدور الأول', null],
        ['تركيب الأسقف المعلقة في الممرات', null],
        ['أعمال الدهانات الأولية للواجهة', 'توقف العمل ساعتين بسبب الغبار'],
        ['تمديدات السباكة وتجربة الضغط', null],
    ];

    private const CLAIM_ITEMS = [
        ['الهيكل الخرساني', 'خرسانة مسلحة للأساسات والأعمدة', 'Reinforced concrete for foundations and columns', '120', '950'],
        ['الهيكل الخرساني', 'بلوك جدران خارجية', 'External block walls', '850', '65'],
        ['التشطيبات', 'بلاط أرضيات بورسلان', 'Porcelain floor tiles', '640', '120'],
        ['التشطيبات', 'دهانات داخلية', 'Interior paint', '1900', '28'],
        ['الكهروميكانيك', 'تمديدات كهرباء كاملة', 'Complete electrical works', '1', '68000'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SiteDemoSeeder is for local use only.');
        }

        mt_srand(2029);

        $admin = User::role(['management', 'super-admin'])->first() ?? User::query()->firstOrFail();
        $engineers = User::role('engineer')->get();
        $engineers = $engineers->isEmpty() ? collect([$admin]) : $engineers;

        $this->dailyReports($admin);
        $project = $this->claimsProject($admin, $engineers->first());

        if ($project !== null) {
            $this->claims($project, $admin);
            $this->draftClaim($project, $admin);
        }
    }

    /** Reports oldest first, so each moves its stage forward; some days are left without one. */
    private function dailyReports(User $admin): void
    {
        $save = app(SaveDailyReport::class);
        $days = ReportCalendar::workingDays(today()->subDays(30), today()->subDay());
        $days = array_slice($days, -20);

        Project::query()->where('status', ProjectStatus::InProgress->value)->with('manager')->orderBy('id')->get()
            ->each(function (Project $project, int $p) use ($save, $days, $admin) {
                foreach ($days as $i => $day) {
                    if (($i + $p) % 5 === 2 || ($project->start_date !== null && $project->start_date->toDateString() > $day)) {
                        continue;
                    }

                    [$work, $obstacle] = self::WORK[($i + $p) % count(self::WORK)];
                    $stage = $project->stages()->where('status', '!=', StageStatus::Done->value)->orderBy('sort_order')->first();
                    $stages = $stage !== null ? [$stage->id => (string) min(100, (int) $stage->progress + 4 + mt_rand(0, 8))] : [];

                    $save->handle([
                        'project_id' => $project->id,
                        'report_date' => $day,
                        'work_done' => $work,
                        'labour_count' => 6 + mt_rand(0, 14),
                        'obstacles' => $obstacle,
                        'notes' => $i % 7 === 0 ? 'زيارة من استشاري المشروع' : null,
                        'stages' => $stages,
                    ], $project->manager ?? $admin);
                }
            });
    }

    private function claimsProject(User $admin, User $engineer): ?Project
    {
        $client = Client::query()->orderBy('id')->first();
        $service = Lookup::query()->ofType(LookupType::Service)->first();
        $unit = Lookup::query()->ofType(LookupType::Unit)->value('id');

        if ($client === null || $service === null) {
            return null;
        }

        $start = today()->subDays(100);
        $quotation = app(SaveQuotation::class)->handle([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'title_ar' => 'مبنى سكني من دورين - الشامخة',
            'title_en' => 'Two-floor residential building - Al Shamkha',
            'location' => 'الشامخة',
            'issue_date' => $start->copy()->subDays(20)->toDateString(),
            'valid_until' => $start->copy()->addDays(10)->toDateString(),
            'assigned_to' => $admin->id,
            'discount_type' => 'amount',
            'discount_value' => '15000',
            'items' => array_map(fn (array $item) => [
                'section_ar' => $item[0], 'description_ar' => $item[1], 'description_en' => $item[2], 'unit_id' => $unit, 'quantity' => $item[3], 'unit_price' => $item[4],
            ], self::CLAIM_ITEMS),
            'payments' => [['label_ar' => 'حسب المستخلصات', 'percent' => '100']],
        ], $admin);
        app(ChangeQuotationStatus::class)->accept($quotation, $admin);

        $project = app(CreateProjectFromQuotation::class)->handle($quotation, [
            'start_date' => $start->toDateString(),
            'planned_end_date' => today()->addDays(80)->toDateString(),
            'manager_id' => $engineer->id,
            'members' => [],
            'billing_method' => BillingMethod::Claims->value,
        ], $admin);

        // The advance: 10% of the contract before VAT, invoiced as an ordinary invoice.
        $quotation->refresh();
        $advance = bcmul(bcsub((string) $quotation->subtotal, (string) $quotation->discount_amount, 2), '0.10', 2);
        $project->forceFill(['status' => ProjectStatus::InProgress, 'advance_amount' => $advance])->save();

        $invoice = app(SaveInvoice::class)->handle([
            'project_id' => $project->id,
            'issue_date' => $start->toDateString(),
            'due_date' => $start->copy()->addDays(14)->toDateString(),
            'items' => [['description_ar' => 'دفعة مقدمة', 'description_en' => 'Advance payment', 'quantity' => '1', 'unit_price' => $advance]],
        ], $admin);
        app(IssueInvoice::class)->handle($invoice, $admin);

        return $project;
    }

    /** Invoiced, approved and submitted claims in date order. */
    private function claims(Project $project, User $admin): void
    {
        $start = $project->start_date ?? today()->subDays(100);
        $plan = [
            [0, 29, '25', 'invoiced'],
            [30, 59, '50', 'approved'],
            [60, 89, '70', 'submitted'],
        ];

        foreach ($plan as [$from, $to, $percent, $outcome]) {
            $claim = app(StartClaim::class)->handle($project, $admin);
            $this->fill($claim, $start->copy()->addDays($from), $start->copy()->addDays($to), $percent, $admin);
            app(SubmitClaim::class)->handle($claim, $admin);

            if ($outcome === 'submitted') {
                continue;
            }

            $invoice = app(ApproveClaim::class)->handle($claim, $admin);

            if ($outcome === 'invoiced') {
                app(IssueInvoice::class)->handle($invoice, $admin);
            }
        }
    }

    /** A draft claim on another project billed by claims, after its contract items are entered. */
    private function draftClaim(Project $except, User $admin): void
    {
        $project = Project::query()->where('billing_method', BillingMethod::Claims->value)->whereKeyNot($except->id)
            ->whereNotIn('status', Project::closedStatuses())->orderBy('id')->first();

        if ($project === null) {
            return;
        }

        if (! $project->items()->exists()) {
            foreach (array_slice(self::CLAIM_ITEMS, 2) as $index => $item) {
                $project->items()->create([
                    'section_ar' => $item[0], 'description_ar' => $item[1], 'description_en' => $item[2], 'quantity' => $item[3],
                    'unit_price' => $item[4], 'line_total' => bcmul($item[3], $item[4], 2), 'sort_order' => $index + 1,
                ]);
            }
        }

        $claim = app(StartClaim::class)->handle($project, $admin);
        $this->fill($claim, $claim->period_from, $claim->period_to, '20', $admin);
    }

    private function fill(Claim $claim, Carbon $from, Carbon $to, string $percent, User $admin): void
    {
        app(SaveClaim::class)->handle($claim, [
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'lines' => $claim->lines()->pluck('id')->mapWithKeys(fn (int $id) => [$id => ['cumulative_percent' => $percent]])->all(),
        ], $admin);
    }
}
