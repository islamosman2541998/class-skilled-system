<?php

namespace Database\Factories;

use App\Enums\SubcontractStatus;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcontract>
 */
class SubcontractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'SC-T-'.fake()->unique()->numberBetween(1, 99999999),
            'supplier_id' => Supplier::factory()->subcontractor(),
            'project_id' => Project::factory(),
            'scope' => 'أعمال مقاولة باطن',
            'value' => '10000.00',
            'vat_rate' => '5.00',
            'start_date' => today(),
            'status' => SubcontractStatus::Active,
        ];
    }
}
