<?php

return [
    'price_items' => 'Price items',
    'new_item' => 'New item',
    'edit_item' => 'Edit item',
    'description' => 'Description',
    'description_ar' => 'Arabic description',
    'description_en' => 'English description',
    'service' => 'Service',
    'unit' => 'Unit',
    'price' => 'Default price',
    'import' => 'Import from Excel',
    'import_file' => 'File',
    'import_hint' => 'An xlsx or csv file with the columns description_ar, description_en, service, unit, price, active. An item with the same description and unit gets its price updated.',
    'download_template' => 'Download a sample file',
    'import_done' => ':created added, :updated updated',
    'import_nothing' => 'No item was imported',
    'import_errors' => [
        'description_required' => 'Row :row: the description is required',
        'unit_not_found' => 'Row :row: unit not found',
        'service_not_found' => 'Row :row: service not found',
        'price_invalid' => 'Row :row: invalid price',
    ],
];
