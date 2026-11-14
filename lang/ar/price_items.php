<?php

return [
    'price_items' => 'بنود الأسعار',
    'new_item' => 'بند جديد',
    'edit_item' => 'تعديل بند',
    'description' => 'الوصف',
    'description_ar' => 'الوصف بالعربية',
    'description_en' => 'الوصف بالإنجليزية',
    'service' => 'الخدمة',
    'unit' => 'الوحدة',
    'price' => 'السعر الافتراضي',
    'import' => 'استيراد من Excel',
    'import_file' => 'الملف',
    'import_hint' => 'ملف xlsx أو csv بأعمدة: description_ar، description_en، service، unit، price، active. البند الموجود بنفس الوصف والوحدة يتحدث سعره.',
    'download_template' => 'تنزيل ملف نموذجي',
    'import_done' => 'تمت إضافة :created وتحديث :updated',
    'import_nothing' => 'لم يتم استيراد أي بند',
    'import_errors' => [
        'description_required' => 'الصف :row: الوصف مطلوب',
        'unit_not_found' => 'الصف :row: الوحدة غير موجودة',
        'service_not_found' => 'الصف :row: الخدمة غير موجودة',
        'price_invalid' => 'الصف :row: السعر غير صالح',
    ],
];
