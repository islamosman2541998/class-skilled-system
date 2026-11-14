<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'PRJ-T-'.fake()->unique()->numberBetween(1, 99999999),
            'title_ar' => 'مشروع '.fake()->unique()->numberBetween(1, 9999999),
            'client_id' => Client::factory(),
            'original_value' => '100000',
            'contract_value' => '100000',
            'vat_rate' => '5.00',
            'billing_method' => 'installments',
            'start_date' => today(),
            'planned_end_date' => today()->addMonths(3),
            'manager_id' => User::factory(),
            'status' => 'in_progress',
        ];
    }
}
