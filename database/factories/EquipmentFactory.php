<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_ar' => 'معدة '.fake()->unique()->numberBetween(1000, 999999),
            'ownership' => 'owned',
            'daily_cost' => '200.00',
            'is_active' => true,
        ];
    }
}
