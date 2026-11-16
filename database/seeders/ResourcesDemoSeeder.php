<?php

namespace Database\Seeders;

use App\Actions\Resources\AllocatePaymentVoucher;
use App\Actions\Resources\BillSubcontractPayment;
use App\Actions\Resources\CancelPaymentVoucher;
use App\Actions\Resources\SaveAttendance;
use App\Actions\Resources\SaveEquipmentAssignment;
use App\Actions\Resources\SavePaymentVoucher;
use App\Actions\Resources\SavePurchase;
use App\Actions\Resources\SaveSubcontract;
use App\Actions\Site\CreateRetentionInvoice;
use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Models\Equipment;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use App\Services\Site\ReportCalendar;
use DomainException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Local resources demo data (Stage 5): suppliers and subcontractors, purchases on running projects
 * (paid, partly paid and unpaid), two subcontracts with billed and unbilled lines, payment vouchers
 * including a cancelled one, workers with 20 working days of attendance, equipment with assignments,
 * and a retention invoice on a delivered claims project when one exists. Everything goes through
 * the Stage 5 actions. Run after ProjectsDemoSeeder, or through DemoSeeder.
 */
class ResourcesDemoSeeder extends Seeder
{
    private const SUPPLIERS = [
        ['مؤسسة الخليج لمواد البناء', 'Gulf Building Materials', 0],
        ['شركة الإمارات للحديد', 'Emirates Steel Trading', 1],
        ['مصنع الشارقة للعزل', 'Sharjah Insulation Factory', 0],
        ['مؤسسة النور للكهرباء', 'Al Noor Electrical', 2],
        ['شركة الواحة للسباكة', 'Oasis Plumbing Supplies', 3],
        ['شركة الإمارات للدهانات', 'Emirates Paints', 4],
        ['مؤسسة الريم للمعدات', 'Al Reem Equipment Hire', 6],
        ['مصنع أبوظبي للجبس', 'Abu Dhabi Gypsum Factory', 5],
    ];

    private const SUBCONTRACTORS = [
        ['مقاولات الريم للجبس والديكور', 'Al Reem Gypsum Contracting', 5],
        ['مؤسسة الفجر للأعمال الكهربائية', 'Al Fajr Electrical Works', 2],
        ['مقاولات الساحل للعزل', 'Coast Insulation Contracting', 0],
    ];

    private const MATERIALS = [
        ['أسمنت بورتلاندي', 'Portland cement', '120', '18.50'],
        ['حديد تسليح 16 مم', 'Rebar 16 mm', '4.5', '2650'],
        ['بلوك أسمنتي 20 سم', 'Concrete block 20 cm', '1500', '3.20'],
        ['رمل ناعم', 'Fine sand', '30', '55'],
        ['كيابل كهربائية 4 مم', 'Electrical cable 4 mm', '20', '185'],
        ['مواسير بي بي آر', 'PPR pipes', '60', '24'],
        ['دهان داخلي', 'Interior paint', '40', '95'],
        ['ألواح جبس', 'Gypsum boards', '120', '28'],
    ];

    private const WORKERS = [
        ['راجيش كومار', 'Rajesh Kumar', 0, '140'], ['محمد إقبال', 'Mohammed Iqbal', 1, '160'],
        ['أنور حسين', 'Anwar Hussain', 2, '150'], ['سوريش بابو', 'Suresh Babu', 3, '170'],
        ['عبد الرحيم', 'Abdul Rahim', 4, '165'], ['جوزيف توماس', 'Joseph Thomas', 5, '145'],
        ['كريم الله', 'Karimullah', 6, '110'], ['ديليب سينغ', 'Dilip Singh', 6, '110'],
        ['نور الإسلام', 'Noor Islam', 0, '140'], ['عمران خان', 'Imran Khan', 1, '155'],
        ['سانجاي', 'Sanjay', 6, '105'], ['فيصل أحمد', 'Faisal Ahmed', 2, '150'],
    ];

    private const EQUIPMENT = [
        ['رافعة برجية', 'Tower crane', 0, 'rented', '850'],
        ['حفار صغير', 'Mini excavator', 1, 'rented', '600'],
        ['خلاطة خرسانة', 'Concrete mixer', 2, 'owned', '120'],
        ['سقالات معدنية', 'Steel scaffolding', 3, 'owned', '90'],
        ['مولد كهرباء 60 ك.ف', 'Generator 60 kVA', 4, 'rented', '250'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('ResourcesDemoSeeder is for local use only.');
        }

        mt_srand(2030);

        $admin = User::role(['management', 'super-admin'])->first() ?? User::query()->firstOrFail();
        $projects = Project::query()->where('status', ProjectStatus::InProgress->value)->with('manager')->orderBy('id')->get();

        if ($projects->isEmpty()) {
            return;
        }

        [$suppliers, $subcontractors] = $this->suppliers($admin);
        $purchases = $this->purchases($projects, $suppliers, $admin);
        $this->subcontracts($projects, $subcontractors, $admin);
        $this->vouchers($purchases, $admin);
        $this->attendance($projects, $admin);
        $this->equipment($projects, $admin);
        $this->retention($admin);
    }

    /**
     * @return array{0: Collection<int, Supplier>, 1: Collection<int, Supplier>}
     */
    private function suppliers(User $admin): array
    {
        $specialties = Lookup::query()->ofType(LookupType::SupplierSpecialty)->orderBy('sort_order')->pluck('id')->values();
        $make = function (array $row, string $type, int $index) use ($specialties, $admin) {
            $supplier = new Supplier([
                'type' => $type, 'name_ar' => $row[0], 'name_en' => $row[1], 'specialty_id' => $specialties[$row[2]] ?? null,
                'phone' => '+97125'.str_pad((string) (500000 + $index * 37), 6, '0', STR_PAD_LEFT), 'is_active' => true,
                'trn' => $index % 2 === 0 ? '1003'.str_pad((string) (45678900 + $index), 11, '0', STR_PAD_LEFT) : null,
            ]);
            $supplier->created_by = $admin->id;
            $supplier->save();

            return $supplier;
        };

        $suppliers = collect(self::SUPPLIERS)->map(fn (array $row, int $i) => $make($row, 'supplier', $i));
        $subcontractors = collect(self::SUBCONTRACTORS)->map(fn (array $row, int $i) => $make($row, 'subcontractor', 20 + $i));

        return [$suppliers, $subcontractors];
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, Supplier>  $suppliers
     * @return Collection<int, Purchase>
     */
    private function purchases(Collection $projects, Collection $suppliers, User $admin): Collection
    {
        $save = app(SavePurchase::class);
        $unit = Lookup::query()->ofType(LookupType::Unit)->value('id');
        $purchases = collect();

        for ($i = 0; $i < 18; $i++) {
            $project = $projects[$i % $projects->count()];
            $supplier = $suppliers[$i % $suppliers->count()];
            [$ar, $en, $qty, $price] = self::MATERIALS[$i % count(self::MATERIALS)];
            $date = today()->subDays(60 - $i * 3);

            $purchases->push($save->handle([
                'supplier_id' => $supplier->id,
                'project_id' => $project->id,
                'purchase_date' => $date->toDateString(),
                'supplier_invoice_no' => 'S'.(2400 + $i),
                'vat' => $i % 5 !== 4,
                'items' => [['description_ar' => $ar, 'description_en' => $en, 'unit_id' => $unit, 'quantity' => $qty, 'unit_price' => $price]],
            ], $project->manager ?? $admin));
        }

        return $purchases;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, Supplier>  $subcontractors
     */
    private function subcontracts(Collection $projects, Collection $subcontractors, User $admin): void
    {
        $plans = [
            ['أعمال الجبس والأسقف المعلقة للدورين', '48000', [['دفعة مقدمة', '9600'], ['عند إنجاز الدور الأرضي', '19200'], ['عند التسليم', '19200']], 2],
            ['التمديدات الكهربائية الكاملة', '36000', [['عند التمديد', '18000'], ['عند التشغيل', '18000']], 1],
        ];

        foreach ($plans as $i => [$scope, $value, $lines, $billed]) {
            $subcontract = app(SaveSubcontract::class)->handle([
                'supplier_id' => $subcontractors[$i]->id,
                'project_id' => $projects[$i % $projects->count()]->id,
                'scope' => $scope,
                'value' => $value,
                'vat' => true,
                'start_date' => today()->subDays(50)->toDateString(),
                'end_date' => today()->addDays(60)->toDateString(),
                'payments' => array_map(fn (array $line) => ['label_ar' => $line[0], 'amount' => $line[1]], $lines),
            ], $admin);

            foreach ($subcontract->payments->take($billed) as $payment) {
                app(BillSubcontractPayment::class)->handle($payment, $admin);
            }
        }
    }

    /**
     * Full, partial and no payment over the purchases; one voucher cancelled.
     *
     * @param  Collection<int, Purchase>  $purchases
     */
    private function vouchers(Collection $purchases, User $admin): void
    {
        $method = Lookup::query()->ofType(LookupType::PaymentMethod)->value('id');
        $save = app(SavePaymentVoucher::class);
        $allocate = app(AllocatePaymentVoucher::class);

        foreach ($purchases->values() as $i => $purchase) {
            $share = match ($i % 3) {
                0 => (string) $purchase->total,
                1 => bcmul((string) $purchase->total, '0.5', 2),
                default => null,
            };

            if ($share === null) {
                continue;
            }

            $voucher = $save->handle([
                'supplier_id' => $purchase->supplier_id,
                'voucher_date' => $purchase->purchase_date->copy()->addDays(10)->min(today())->toDateString(),
                'amount' => $share,
                'payment_method_id' => $method,
                'reference' => 'TRX-'.(7100 + $i),
            ], $admin);
            $allocate->handle($voucher, [$purchase->id => $share], $admin);

            if ($i === 4) {
                app(CancelPaymentVoucher::class)->handle($voucher, 'شيك مرتجع من البنك', $admin);
            }
        }
    }

    /**
     * 12 workers over the last 20 working days, spread over the running projects.
     *
     * @param  Collection<int, Project>  $projects
     */
    private function attendance(Collection $projects, User $admin): void
    {
        $trades = Lookup::query()->ofType(LookupType::WorkerTrade)->orderBy('sort_order')->pluck('id')->values();
        $workers = collect(self::WORKERS)->map(function (array $row, int $i) use ($trades, $admin) {
            $worker = new Worker(['name_ar' => $row[0], 'name_en' => $row[1], 'trade_id' => $trades[$row[2]] ?? null, 'daily_wage' => $row[3], 'is_company' => $i < 9, 'is_active' => true]);
            $worker->created_by = $admin->id;
            $worker->save();

            return $worker;
        });

        $days = array_slice(ReportCalendar::workingDays(today()->subDays(30), today()->subDay()), -20);
        $sites = $projects->take(3)->values();
        $save = app(SaveAttendance::class);

        foreach ($days as $d => $day) {
            foreach ($sites as $s => $project) {
                $rows = [];

                foreach ($workers->values() as $w => $worker) {
                    if ($w % $sites->count() !== $s) {
                        continue;
                    }

                    $roll = ($d + $w) % 9;
                    $rows[$worker->id] = [
                        'status' => match (true) {
                            $roll === 0 => 'absent',
                            $roll === 1 => 'half',
                            default => 'present',
                        },
                        'overtime_hours' => $roll === 5 ? '2' : '0',
                    ];
                }

                $save->handle($project, Carbon::parse($day), $rows, $project->manager ?? $admin);
            }
        }
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function equipment(Collection $projects, User $admin): void
    {
        $types = Lookup::query()->ofType(LookupType::EquipmentType)->orderBy('sort_order')->pluck('id')->values();
        $save = app(SaveEquipmentAssignment::class);

        foreach (self::EQUIPMENT as $i => [$ar, $en, $type, $ownership, $daily]) {
            $equipment = new Equipment(['name_ar' => $ar, 'name_en' => $en, 'type_id' => $types[$type] ?? null, 'ownership' => $ownership, 'daily_cost' => $daily, 'is_active' => true]);
            $equipment->created_by = $admin->id;
            $equipment->save();

            $save->handle([
                'equipment_id' => $equipment->id,
                'project_id' => $projects[$i % $projects->count()]->id,
                'date_from' => today()->subDays(40 - $i * 4)->toDateString(),
                'date_to' => today()->subDays(25 - $i * 4)->toDateString(),
            ], $admin);

            if ($i < 2) {
                $save->handle([
                    'equipment_id' => $equipment->id,
                    'project_id' => $projects[($i + 1) % $projects->count()]->id,
                    'date_from' => today()->subDays(15)->toDateString(),
                    'date_to' => today()->subDays(5)->toDateString(),
                ], $admin);
            }
        }
    }

    /** A retention invoice on a delivered project billed by claims, when one exists. */
    private function retention(User $admin): void
    {
        $project = Project::query()->where('billing_method', 'claims')->has('handover')->whereHas('claims')->first();

        if ($project === null) {
            return;
        }

        try {
            app(CreateRetentionInvoice::class)->handle($project, $admin);
        } catch (DomainException) {
            // Nothing retained yet; the demo simply has no retention invoice.
        }
    }
}
