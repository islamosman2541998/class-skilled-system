<?php

namespace Database\Seeders;

use App\Actions\Projects\CreateProjectFromQuotation;
use App\Actions\Projects\DecideVariation;
use App\Actions\Projects\SaveProject;
use App\Actions\Projects\SaveVariation;
use App\Enums\BillingMethod;
use App\Enums\DocumentType;
use App\Enums\LookupType;
use App\Enums\MaintenanceStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\StageStatus;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Models\Quotation;
use App\Models\StageTemplate;
use App\Models\User;
use App\Services\InstallmentSchedule;
use App\Services\NumberGenerator;
use App\Services\ProjectProgress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Local projects demo data (Stage 2): projects from accepted quotations and direct ones in every
 * status, with stages, payment schedules, variations, handovers and maintenance requests.
 * Run after SalesDemoSeeder, or through DemoSeeder.
 */
class ProjectsDemoSeeder extends Seeder
{
    private const STAGES = [
        ['التجهيز والتحضير', 'Preparation', 10],
        ['الأعمال الرئيسية', 'Main works', 50],
        ['التشطيب', 'Finishing', 30],
        ['الفحص والتسليم', 'Inspection and handover', 10],
    ];

    /** Status of each direct project, in order; "late" is a running project past its planned end. */
    private const PLAN = [
        'not_started', 'in_progress', 'in_progress', 'late', 'late', 'on_hold',
        'completed', 'delivered', 'delivered', 'delivered', 'cancelled', 'in_progress',
    ];

    private const ISSUES = [
        'تسريب مياه عند فتحة التصريف', 'تشقق في طبقة الحماية', 'انتفاخ في أرضية الإيبوكسي',
        'تقشر الدهان في الممر', 'رطوبة في الجدار الخارجي', 'صوت طقطقة في بلاط المدخل',
        'تسريب حول وحدة التكييف', 'هبوط بسيط في البلاط', 'شرخ في سقف الجبس', 'تغير لون العزل',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('ProjectsDemoSeeder is for local use only.');
        }

        mt_srand(2027);

        $users = User::query()->where('is_active', true)->get();
        $admin = User::role(['management', 'super-admin'])->first() ?? $users->first();
        $engineers = User::role('engineer')->get();
        $engineers = $engineers->isEmpty() ? $users : $engineers;
        $services = Lookup::query()->ofType(LookupType::Service)->get();
        $emirates = Lookup::query()->ofType(LookupType::Emirate)->get();

        $this->templates($services);

        $plans = [];
        $projects = $this->fromQuotations($admin, $engineers, $plans)->merge($this->direct($admin, $engineers, $services, $emirates, $plans));

        foreach ($projects->values() as $index => $project) {
            $this->stages($project, $plans[$project->id] ?? 'in_progress', $index);
        }

        $this->variations($projects, $admin);
        $this->handovers($projects, $admin);
        $this->maintenance($projects, $engineers, $admin);
    }

    /** Stage templates for the first two services, so new projects start with stages. */
    private function templates(Collection $services): void
    {
        foreach ($services->take(2) as $service) {
            if (StageTemplate::query()->where('service_id', $service->id)->exists()) {
                continue;
            }

            foreach (self::STAGES as $index => [$ar, $en, $weight]) {
                StageTemplate::query()->create(['service_id' => $service->id, 'name_ar' => $ar, 'name_en' => $en, 'weight' => $weight, 'sort_order' => $index + 1]);
            }
        }
    }

    /**
     * @param  array<int, string>  $plans  planned status per project id
     * @return Collection<int, Project>
     */
    private function fromQuotations(User $admin, Collection $engineers, array &$plans): Collection
    {
        $convert = app(CreateProjectFromQuotation::class);

        return Quotation::query()->where('status', QuotationStatus::Accepted->value)->doesntHave('project')->get()
            ->values()
            ->map(function (Quotation $quotation, int $i) use ($convert, $admin, $engineers, &$plans) {
                $project = $convert->handle($quotation, [
                    'start_date' => now()->subDays(max(5, 60 - $i * 5))->toDateString(),
                    'planned_end_date' => now()->addDays(30 + $i * 10)->toDateString(),
                    'manager_id' => $engineers[$i % $engineers->count()]->id,
                    'members' => [$engineers[($i + 1) % $engineers->count()]->id],
                    'billing_method' => BillingMethod::Installments->value,
                ], $admin);
                $plans[$project->id] = $i % 2 === 0 ? 'in_progress' : 'not_started';

                return $project;
            });
    }

    /**
     * @param  array<int, string>  $plans  planned status per project id
     * @return Collection<int, Project>
     */
    private function direct(User $admin, Collection $engineers, Collection $services, Collection $emirates, array &$plans): Collection
    {
        $save = app(SaveProject::class);
        $clients = Client::query()->orderBy('id')->take(count(self::PLAN))->get();
        $projects = collect();

        if ($clients->isEmpty()) {
            return $projects;
        }

        foreach (self::PLAN as $i => $plan) {
            $service = $services[$i % $services->count()];
            $client = $clients[$i % $clients->count()];
            $start = now()->subDays(match ($plan) {
                'not_started' => -10,
                'delivered' => 600 - $i * 40,
                'completed' => 120,
                'late' => 150,
                default => 45 + $i * 3,
            });
            $plannedEnd = $plan === 'late' ? now()->subDays(5 + $i) : $start->copy()->addDays(90);

            $project = $save->handle([
                'client_id' => $client->id,
                'title_ar' => $service->name_ar.' - '.($client->address ?: 'أبوظبي'),
                'title_en' => ($service->name_en ?: $service->name_ar).' - '.($client->address ?: 'Abu Dhabi'),
                'service_id' => $service->id,
                'emirate_id' => $emirates[$i % $emirates->count()]->id,
                'location' => $client->address,
                'original_value' => (string) (40000 + ($i * 17345) % 260000),
                'billing_method' => $i % 4 === 3 ? BillingMethod::Claims->value : BillingMethod::Installments->value,
                'start_date' => $start->toDateString(),
                'planned_end_date' => $plannedEnd->toDateString(),
                'manager_id' => $engineers[$i % $engineers->count()]->id,
                'members' => [$engineers[($i + 2) % $engineers->count()]->id],
                'description' => 'مشروع '.$service->name_ar.' للعميل '.$client->name_ar,
            ], $admin);

            InstallmentSchedule::fromPercents($project, [
                ['label_ar' => 'دفعة مقدمة', 'label_en' => 'Advance payment', 'percent' => '30'],
                ['label_ar' => 'عند إنجاز الأعمال الرئيسية', 'label_en' => 'On main works completion', 'percent' => '50'],
                ['label_ar' => 'عند التسليم', 'label_en' => 'On handover', 'percent' => '20'],
            ]);

            $plans[$project->id] = $plan;
            $projects->push($project);
        }

        return $projects;
    }

    /** Stages with dates and progress matching the planned status; the status is set last. */
    private function stages(Project $project, string $plan, int $index): void
    {
        if ($project->stages()->doesntExist()) {
            foreach (self::STAGES as $order => [$ar, $en, $weight]) {
                $project->stages()->create(['name_ar' => $ar, 'name_en' => $en, 'weight' => $weight, 'sort_order' => $order + 1, 'status' => StageStatus::NotStarted]);
            }
        }

        $stages = $project->stages()->orderBy('sort_order')->get();
        $start = $project->start_date ?? today();
        $span = max(4, (int) $start->diffInDays($project->planned_end_date ?? $start->copy()->addDays(90)));
        $doneUntil = match ($plan) {
            'completed', 'delivered' => $stages->count(),
            'in_progress', 'late', 'on_hold', 'cancelled' => 1 + $index % 2,
            default => 0,
        };

        foreach ($stages as $order => $stage) {
            $from = $start->copy()->addDays(intdiv($span * $order, $stages->count()));
            $to = $start->copy()->addDays(intdiv($span * ($order + 1), $stages->count()));
            $done = $order < $doneUntil;
            $current = ! $done && $order === $doneUntil && in_array($plan, ['in_progress', 'late', 'on_hold'], true);

            $stage->fill([
                'planned_start' => $from,
                'planned_end' => $to,
                'actual_start' => $done || $current ? $from : null,
                'actual_end' => $done ? $to : null,
                'status' => $done ? StageStatus::Done : ($current ? StageStatus::InProgress : StageStatus::NotStarted),
                'progress' => $done ? 100 : ($current ? 40 + ($index * 7) % 50 : 0),
            ])->save();
        }

        ProjectProgress::refresh($project);

        $project->refresh();
        $project->status = match ($plan) {
            'late' => ProjectStatus::InProgress,
            default => ProjectStatus::from($plan),
        };
        $project->actual_end_date = in_array($plan, ['completed', 'delivered'], true) ? $project->planned_end_date : null;
        $project->save();
    }

    private function variations(Collection $projects, User $admin): void
    {
        $save = app(SaveVariation::class);
        $decide = app(DecideVariation::class);
        $unit = Lookup::query()->ofType(LookupType::Unit)->first();

        foreach ($projects->values()->filter(fn (Project $project, int $i) => $i % 3 === 1)->values() as $i => $project) {
            $variation = $save->handle($project, [
                'description' => ['أعمال إضافية في السطح', 'إلغاء جزء من التشطيب', 'توسعة مساحة العزل'][$i % 3],
                'items' => [[
                    'description_ar' => ['عزل مائي إضافي', 'إلغاء دهان داخلي', 'طبقة حماية إضافية'][$i % 3],
                    'description_en' => ['Extra waterproofing', 'Internal paint removed', 'Extra protection layer'][$i % 3],
                    'unit_id' => $unit?->id,
                    'quantity' => $i % 3 === 1 ? '-40' : (string) (30 + $i * 10),
                    'unit_price' => (string) (45 + $i * 5),
                ]],
            ], $admin);

            match ($i % 3) {
                0 => $decide->approve($variation, $admin),
                1 => $decide->reject($variation, 'العميل لم يوافق على السعر', $admin),
                default => null,
            };
        }
    }

    private function handovers(Collection $projects, User $admin): void
    {
        $projects->filter(fn (Project $project) => $project->fresh()?->status === ProjectStatus::Delivered)
            ->values()
            ->each(function (Project $project, int $i) use ($admin) {
                $date = ($project->planned_end_date ?? today())->copy();
                $months = [12, 3, 24][$i % 3];

                $project->handover()->firstOrCreate([], [
                    'handover_date' => $date,
                    'warranty_months' => $months,
                    'warranty_end' => ProjectHandover::warrantyEnd($date, $months),
                    'notes' => 'تم التسليم بحضور المالك',
                    'created_by' => $admin->id,
                ]);
            });
    }

    private function maintenance(Collection $projects, Collection $engineers, User $admin): void
    {
        $numbers = app(NumberGenerator::class);
        $delivered = Project::query()->with('handover')->whereKey($projects->pluck('id'))->where('status', ProjectStatus::Delivered->value)->get();

        if ($delivered->isEmpty()) {
            return;
        }

        foreach (self::ISSUES as $i => $issue) {
            $project = $delivered[$i % $delivered->count()];
            $date = today()->subDays(3 + $i * 9);
            $status = [MaintenanceStatus::Open, MaintenanceStatus::InProgress, MaintenanceStatus::Done, MaintenanceStatus::Cancelled][$i % 4];

            $request = new MaintenanceRequest([
                'project_id' => $project->id,
                'request_date' => $date,
                'description' => $issue,
                'status' => $status,
                'assigned_to' => $i % 5 === 4 ? null : $engineers[$i % $engineers->count()]->id,
                'notes' => $status === MaintenanceStatus::Done ? 'تمت المعالجة وفحص الموقع' : null,
            ]);
            $request->number = $numbers->next(DocumentType::Maintenance);
            $request->in_warranty = MaintenanceRequest::warrantyCovers($project, $date);
            $request->cost = $request->in_warranty ? '0' : (string) (300 + $i * 150);
            $request->created_by = $admin->id;
            $request->save();
        }
    }
}
