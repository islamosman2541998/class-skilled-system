<?php

namespace Database\Factories;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_ar' => 'عامل '.fake()->unique()->numberBetween(1000, 999999),
            'daily_wage' => '120.00',
            'is_company' => true,
            'is_active' => true,
        ];
    }
}
