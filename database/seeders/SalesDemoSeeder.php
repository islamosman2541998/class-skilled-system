<?php

namespace Database\Seeders;

use App\Actions\Leads\EnsureClientForLead;
use App\Actions\Quotations\ChangeQuotationStatus;
use App\Actions\Quotations\ReviseQuotation;
use App\Actions\Quotations\SaveQuotation;
use App\Enums\DocumentType;
use App\Enums\FollowUpType;
use App\Enums\LookupType;
use App\Enums\SiteVisitStatus;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\PriceItem;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Settings\TemplateSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Local sales demo data (Stage 1): clients, requests with follow-ups, visits, price list and
 * quotations in every state. Run after DemoSeeder created the users, or through it.
 */
class SalesDemoSeeder extends Seeder
{
    private const PEOPLE = ['سالم الكعبي', 'حمد البلوشي', 'راشد النقبي', 'مريم الظاهري', 'خالد المنصوري', 'فاطمة الحمادي', 'سعيد الكتبي', 'عائشة الشامسي', 'ناصر السويدي', 'هند المزروعي', 'عبدالله النعيمي', 'ليلى الشحي', 'محمد الرميثي', 'نورة المهيري', 'سلطان الدرمكي', 'شمسة الفلاسي', 'أحمد الجنيبي', 'موزة القبيسي', 'يوسف العامري', 'علياء البادي'];

    private const COMPANIES = ['مؤسسة الريم للتطوير العقاري', 'شركة الواحة للاستثمار', 'مجموعة النخيل القابضة', 'شركة الأفق للإنشاءات', 'مؤسسة الصقر التجارية', 'شركة البحر الأزرق للعقارات', 'استوديوهات الضوء للإنتاج', 'شركة الخليج للمستودعات', 'مدرسة المستقبل الخاصة', 'فندق الكورنيش', 'مجمع الياسمين السكني', 'شركة الرمال للمقاولات', 'مؤسسة السدرة للتجارة', 'مركز الشفاء الطبي', 'شركة الفنار للتقنية', 'مطعم بيت الجدة', 'شركة الظفرة الزراعية', 'نادي الشاطئ الرياضي', 'مؤسسة القمة للديكور', 'شركة المرجان البحرية'];

    private const AREAS = ['خليفة أ', 'مدينة محمد بن زايد', 'الريم', 'السعديات', 'الشامخة', 'البطين', 'مصفح', 'الخليج التجاري', 'الجداف', 'القوز'];

    private const ITEMS = [
        ['عزل مائي بالبيتومين طبقتين', 'Two-layer bitumen waterproofing', 'م²', 42, 55],
        ['عزل حراري بألواح البوليسترين', 'Polystyrene board thermal insulation', 'م²', 30, 40],
        ['طبقة حماية خرسانية للعزل', 'Concrete protection screed', 'م²', 25, 35],
        ['أرضية إيبوكسي ذاتية التسوية 3 مم', 'Self-levelling epoxy floor 3 mm', 'م²', 85, 120],
        ['دهان إيبوكسي للمواقف', 'Epoxy coating for parking', 'م²', 45, 60],
        ['لياسة داخلية', 'Internal plaster', 'م²', 22, 30],
        ['دهان داخلي ثلاث طبقات', 'Internal paint, three coats', 'م²', 18, 25],
        ['بلاط بورسلان 60×60 مع التركيب', 'Porcelain tiles 60x60 with fixing', 'م²', 95, 140],
        ['أسقف جبس بورد', 'Gypsum board ceilings', 'م²', 65, 90],
        ['عزل صوتي للجدران', 'Wall acoustic insulation', 'م²', 110, 160],
        ['صب خرسانة مسلحة', 'Reinforced concrete casting', 'م³', 850, 1100],
        ['هدم وإزالة مخلفات', 'Demolition and debris removal', 'مقطوعية', 2500, 6000],
        ['تنظيف نهائي للموقع', 'Final site cleaning', 'مقطوعية', 800, 1500],
        ['أعمال تبليط المسبح', 'Pool tiling works', 'م²', 160, 220],
        ['نظام فلترة المسبح', 'Pool filtration system', 'عدد', 9000, 15000],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SalesDemoSeeder is for local use only.');
        }

        mt_srand(2026);

        $users = User::query()->where('is_active', true)->get();
        $sales = User::role(['sales', 'management', 'super-admin'])->get();
        $engineers = User::role('engineer')->get();
        $sales = $sales->isEmpty() ? $users : $sales;
        $engineers = $engineers->isEmpty() ? $users : $engineers;
        $admin = $sales->first();

        $services = Lookup::query()->ofType(LookupType::Service)->get();
        $sources = Lookup::query()->ofType(LookupType::LeadSource)->get();
        $emirates = Lookup::query()->ofType(LookupType::Emirate)->get();
        $reasons = Lookup::query()->ofType(LookupType::RejectionReason)->where('is_active', true)->get();

        $this->priceItems($services);
        $clients = $this->clients($sources, $emirates, $admin);
        $leads = $this->leads($clients, $services, $sources, $emirates, $sales, $reasons, $admin);
        $this->visits($leads, $engineers, $admin);
        $this->quotations($leads, $sales, $reasons);
    }

    private function priceItems(Collection $services): void
    {
        $units = Lookup::query()->ofType(LookupType::Unit)->pluck('id', 'name_ar');

        foreach (self::ITEMS as $index => [$ar, $en, $unit, $min, $max]) {
            foreach (['', ' — جودة عالية', ' — اقتصادي', ' مع الضمان'] as $variant => $suffix) {
                PriceItem::query()->firstOrCreate(
                    ['description_ar' => $ar.$suffix, 'unit_id' => $units[$unit]],
                    [
                        'description_en' => $en.($variant ? ' ('.['', 'premium', 'economy', 'with warranty'][$variant].')' : ''),
                        'service_id' => $services[$index % $services->count()]->id,
                        'default_price' => (string) round($min + ($max - $min) * ($variant / 3), 2),
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @return Collection<int, Client>
     */
    private function clients(Collection $sources, Collection $emirates, User $by): Collection
    {
        $clients = collect();

        foreach (array_merge(self::PEOPLE, self::COMPANIES) as $index => $name) {
            $company = $index >= count(self::PEOPLE);
            $client = Client::query()->firstOrCreate(['name_ar' => $name], [
                'type' => $company ? 'company' : 'individual',
                'phone' => '05'.(0 + $index % 9).sprintf('%07d', 1000000 + $index * 7919 % 8999999),
                'email' => $company ? 'info'.$index.'@example.ae' : null,
                'trn' => $company ? '100'.sprintf('%012d', 100000000 + $index) : null,
                'emirate_id' => $emirates[$index % 2]->id,
                'address' => self::AREAS[$index % count(self::AREAS)],
                'source_id' => $sources[$index % $sources->count()]->id,
                'created_by' => $by->id,
            ]);

            if ($company && $client->contacts()->doesntExist()) {
                $client->contacts()->create(['name' => self::PEOPLE[$index % count(self::PEOPLE)], 'position' => 'مدير المشروع', 'phone' => '056'.sprintf('%07d', 2000000 + $index)]);
            }

            $clients->push($client);
        }

        return $clients;
    }

    /**
     * @return Collection<int, Lead>
     */
    private function leads(Collection $clients, Collection $services, Collection $sources, Collection $emirates, Collection $sales, Collection $reasons, User $by): Collection
    {
        $numbers = app(NumberGenerator::class);
        $codes = ['new', 'new', 'contacted', 'contacted', 'visit', 'quoted', 'won', 'lost'];
        $leads = collect();

        for ($i = 0; $i < 80; $i++) {
            $withClient = $i % 3 === 0;
            $code = $codes[$i % count($codes)];
            $lead = new Lead([
                'client_id' => $withClient ? $clients[$i % $clients->count()]->id : null,
                'contact_name' => $withClient ? null : self::PEOPLE[($i * 3) % count(self::PEOPLE)],
                'contact_phone' => $withClient ? null : '055'.sprintf('%07d', 3000000 + $i * 37),
                'service_id' => $services[$i % $services->count()]->id,
                'facility_type' => ['فيلا', 'مستودع', 'مكتب', 'مدرسة', 'مطعم'][$i % 5],
                'emirate_id' => $emirates[$i % 2]->id,
                'area_text' => self::AREAS[$i % count(self::AREAS)],
                'area_m2' => (string) (150 + ($i * 37) % 900),
                'expected_start' => ['خلال أسبوع', 'خلال شهر', 'بعد شهرين'][$i % 3],
                'description' => 'طلب '.$services[$i % $services->count()]->name_ar.' لمساحة تقريبية '.(150 + ($i * 37) % 900).' م²',
                'source_id' => $sources[$i % $sources->count()]->id,
                'assigned_to' => $sales[$i % $sales->count()]->id,
                'follow_up_at' => in_array($code, ['won', 'lost'], true) ? null : now()->addDays(($i % 7) - 3)->setTime(10, 0),
                'created_by' => $by->id,
            ]);
            $lead->number = $numbers->next(DocumentType::Lead);
            $lead->status_id = Lead::statusIdFor($code);
            $lead->created_at = now()->subDays(80 - $i);

            if ($code === 'lost') {
                $lead->rejection_reason_id = $reasons->first()?->id;
            }

            $lead->save();

            foreach (array_slice(FollowUpType::cases(), 0, 1 + $i % 3) as $n => $type) {
                $lead->followUps()->create([
                    'type' => $type, 'body' => ['تم الاتصال وطلب العميل معاينة', 'أرسلت للعميل صور أعمال سابقة', 'العميل يقارن بين أكثر من عرض'][$n],
                    'user_id' => $lead->assigned_to, 'happened_at' => $lead->created_at->copy()->addDays($n + 1),
                ]);
            }

            $leads->push($lead);
        }

        return $leads;
    }

    private function visits(Collection $leads, Collection $engineers, User $by): void
    {
        $numbers = app(NumberGenerator::class);
        $ensure = app(EnsureClientForLead::class);

        foreach ($leads->take(30)->values() as $i => $lead) {
            $client = $ensure->handle($lead, $by);
            $past = $i % 3 !== 0;
            $visit = new SiteVisit([
                'lead_id' => $lead->id,
                'client_id' => $client->id,
                'scheduled_at' => $past ? now()->subDays(1 + $i)->setTime(9 + $i % 6, 0) : now()->addDays($i % 4)->setTime(9 + $i % 6, 30),
                'engineer_id' => $engineers[$i % $engineers->count()]->id,
                'address' => $lead->area_text,
                'map_url' => 'https://maps.google.com/?q=24.4'.$i.',54.5'.$i,
                'status' => $past ? ($i % 5 === 0 ? SiteVisitStatus::Cancelled : SiteVisitStatus::Done) : SiteVisitStatus::Scheduled,
                'notes' => $past ? 'تمت المعاينة والعميل يحتاج عرض سعر خلال أسبوع' : null,
                'measurements' => $past ? 'المساحة الفعلية '.$lead->area_m2.' م²' : null,
                'created_by' => $by->id,
            ]);
            $visit->number = $numbers->next(DocumentType::SiteVisit);
            $visit->result_at = $past ? $visit->scheduled_at->copy()->addHours(2) : null;
            $visit->save();
        }
    }

    private function quotations(Collection $leads, Collection $sales, Collection $reasons): void
    {
        $save = app(SaveQuotation::class);
        $status = app(ChangeQuotationStatus::class);
        $revise = app(ReviseQuotation::class);
        $items = PriceItem::query()->get();
        $template = app(TemplateSettings::class);
        $payments = $template->payment_templates[0]['lines'] ?? [['label_ar' => 'دفعة واحدة', 'percent' => '100']];

        foreach ($leads->slice(10, 35)->values() as $i => $lead) {
            $user = $sales[$i % $sales->count()];
            $lines = [];

            foreach ($items->slice(($i * 5) % max(1, $items->count() - 6), 2 + $i % 4) as $n => $item) {
                $lines[] = [
                    'section_ar' => $n < 2 ? 'الأعمال الرئيسية' : 'أعمال إضافية',
                    'price_item_id' => $item->id,
                    'description_ar' => $item->description_ar,
                    'description_en' => $item->description_en,
                    'unit_id' => $item->unit_id,
                    'quantity' => (string) (20 + ($i * 13 + $n * 7) % 400),
                    'unit_price' => $item->default_price,
                ];
            }

            $quotation = $save->handle([
                'lead_id' => $lead->id,
                'service_id' => $lead->service_id,
                'title_ar' => ($lead->service->name_ar ?? 'أعمال').' - '.$lead->area_text,
                'emirate_id' => $lead->emirate_id,
                'location' => $lead->area_text,
                'issue_date' => now()->subDays(40 - $i)->toDateString(),
                'valid_until' => now()->subDays(40 - $i)->addDays(30)->toDateString(),
                'assigned_to' => $user->id,
                'discount_type' => $i % 4 === 0 ? 'percent' : null,
                'discount_value' => $i % 4 === 0 ? '5' : '0',
                'items' => $lines,
                'payments' => $payments,
                'terms_ar' => $template->quotation_terms_ar,
                'terms_en' => $template->quotation_terms_en,
            ], $user);

            match ($i % 6) {
                1, 2 => $status->send($quotation, $user),
                3 => $status->accept($quotation, $user),
                4 => $status->reject($quotation, (int) $reasons->first()?->id, 'السعر أعلى من الميزانية', $user),
                5 => $revise->handle($quotation, $user),
                default => null,
            };
        }
    }
}
