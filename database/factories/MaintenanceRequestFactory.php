<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'MR-T-'.fake()->unique()->numberBetween(1, 99999999),
            'project_id' => Project::factory(),
            'request_date' => today(),
            'description' => fake()->sentence(),
            'in_warranty' => false,
            'status' => 'open',
            'cost' => 0,
        ];
    }
}
