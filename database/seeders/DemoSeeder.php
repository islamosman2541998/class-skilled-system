<?php

namespace Database\Seeders;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local demo data: users in every role and 50,000 list rows for the table speed check (spec §11).
 * Run with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private const USERS = [
        ['أحمد الكعبي', 'management'],
        ['مريم الظاهري', 'management'],
        ['خالد المنصوري', 'sales'],
        ['فاطمة الحمادي', 'sales'],
        ['يوسف النعيمي', 'sales'],
        ['محمد عبد الرحمن', 'engineer'],
        ['عمر حسين', 'engineer'],
        ['سارة إبراهيم', 'engineer'],
        ['علي رضا', 'engineer'],
        ['نورة السويدي', 'accountant'],
        ['حسن الشامسي', 'accountant'],
        ['ليلى المزروعي', 'sales'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder is for local use only.');
        }

        foreach (self::USERS as $index => [$name, $role]) {
            $user = User::query()->firstOrCreate(
                ['email' => "demo{$index}@class-skilled.test"],
                ['name' => $name, 'password' => bin2hex(random_bytes(12)), 'locale' => 'ar', 'is_active' => true, 'phone' => '+97150'.str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT)],
            );
            $user->syncRoles([$role]);
        }

        $this->seedLookups(50000);
    }

    private function seedLookups(int $count): void
    {
        $type = LookupType::RejectionReason->value;
        $existing = Lookup::query()->where('type', $type)->where('code', 'like', 'demo-%')->count();
        $now = now();
        $words = ['السعر مرتفع', 'تأجيل المشروع', 'اختيار مقاول آخر', 'تغيير التصميم', 'عدم توفر التمويل', 'الموقع بعيد', 'مدة التنفيذ طويلة'];

        for ($start = $existing; $start < $count; $start += 1000) {
            $rows = [];

            for ($i = $start; $i < min($start + 1000, $count); $i++) {
                $rows[] = [
                    'type' => $type,
                    'name_ar' => $words[$i % count($words)].' '.($i + 1),
                    'name_en' => 'Reason '.($i + 1),
                    'code' => 'demo-'.($i + 1),
                    'is_active' => $i % 9 !== 0,
                    'is_system' => false,
                    'sort_order' => 1000 + $i,
                    'created_at' => $now->copy()->subMinutes($count - $i),
                    'updated_at' => $now,
                ];
            }

            DB::table('lookups')->insert($rows);
        }

        cache()->forget('lookups.'.$type);
    }
}
