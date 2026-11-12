<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lookup>
 */
class LookupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => LookupType::Unit,
            'name_ar' => 'عنصر '.fake()->unique()->numberBetween(1, 1000000),
            'name_en' => null,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 0,
        ];
    }
}
