<?php

namespace Database\Seeders;

use App\Enums\LookupType;
use App\Models\Lookup;
use Illuminate\Database\Seeder;

/**
 * Starting content of the managed lists. Safe to run again: rows are matched by code or Arabic name
 * and existing rows are left as the user edited them.
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // [name_ar, name_en, code?, color?, is_system?]
        $lists = [
            LookupType::Service->value => [
                ['إنشاء الفلل والمباني السكنية', 'Villas and residential buildings'],
                ['المباني التجارية والحكومية', 'Commercial and government buildings'],
                ['التشطيبات والديكورات الداخلية والخارجية', 'Interior and exterior finishing'],
                ['أرضيات الإيبوكسي', 'Epoxy flooring'],
                ['عزل الأرضيات والأسطح', 'Floor and roof insulation'],
                ['العزل الصوتي', 'Acoustic insulation'],
                ['تجهيز استوديوهات التصوير السينمائي', 'Film studio fit-out'],
                ['البنية التحتية والخرسانة والترميمات', 'Infrastructure, concrete and renovation'],
                ['أعمال المسابح', 'Swimming pools'],
            ],
            LookupType::LeadStatus->value => [
                ['جديد', 'New', 'new', 'info', true],
                ['تم التواصل', 'Contacted', 'contacted', 'neutral', true],
                ['معاينة', 'Site visit', 'visit', 'warning', true],
                ['عرض سعر', 'Quoted', 'quoted', 'warning', true],
                ['تم التعاقد', 'Won', 'won', 'success', true],
                ['مرفوض', 'Lost', 'lost', 'danger', true],
            ],
            LookupType::LeadSource->value => [
                ['الموقع', 'Website', 'website', null, true],
                ['واتساب', 'WhatsApp'],
                ['هاتف', 'Phone'],
                ['زيارة للمكتب', 'Walk-in'],
                ['توصية', 'Referral'],
                ['وسائل التواصل', 'Social media'],
            ],
            LookupType::RejectionReason->value => [
                ['السعر', 'Price'],
                ['اختار مقاولًا آخر', 'Chose another contractor'],
                ['تأجيل المشروع', 'Project postponed'],
                ['خارج نطاق العمل', 'Out of scope'],
                ['لا يوجد رد', 'No response'],
            ],
            LookupType::Unit->value => [
                ['م²', 'm²'],
                ['م.ط', 'Linear m'],
                ['م³', 'm³'],
                ['عدد', 'Piece'],
                ['مقطوعية', 'Lump sum'],
                ['يوم', 'Day'],
                ['طن', 'Ton'],
            ],
            LookupType::ExpenseCategory->value => [
                ['مواد', 'Materials'],
                ['عمالة', 'Labour'],
                ['نقل', 'Transport'],
                ['معدات', 'Equipment'],
                ['مقاول باطن', 'Subcontractor'],
                ['مصروفات إدارية', 'Administrative'],
                ['أخرى', 'Other'],
            ],
            LookupType::AttachmentCategory->value => [
                ['مخططات', 'Drawings'],
                ['عقود', 'Contracts'],
                ['صور', 'Photos'],
                ['فيديو', 'Videos'],
                ['مستندات', 'Documents'],
            ],
            LookupType::Emirate->value => [
                ['أبوظبي', 'Abu Dhabi'],
                ['دبي', 'Dubai'],
                ['الشارقة', 'Sharjah'],
                ['عجمان', 'Ajman'],
                ['أم القيوين', 'Umm Al Quwain'],
                ['رأس الخيمة', 'Ras Al Khaimah'],
                ['الفجيرة', 'Fujairah'],
            ],
            LookupType::PaymentMethod->value => [
                ['نقدي', 'Cash', 'cash'],
                ['تحويل بنكي', 'Bank transfer', 'transfer'],
                ['شيك', 'Cheque', 'cheque'],
            ],
        ];

        foreach ($lists as $type => $rows) {
            foreach ($rows as $index => $row) {
                [$nameAr, $nameEn] = $row;
                $code = $row[2] ?? null;

                $match = $code !== null ? ['type' => $type, 'code' => $code] : ['type' => $type, 'name_ar' => $nameAr];

                Lookup::withTrashed()->firstOrCreate($match, [
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'code' => $code,
                    'color' => $row[3] ?? null,
                    'is_system' => $row[4] ?? false,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }
}
