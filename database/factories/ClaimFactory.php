<?php

namespace Database\Factories;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->state(['billing_method' => 'claims']),
            'client_id' => fn (array $attributes) => Project::query()->find($attributes['project_id'])->client_id,
            'period_from' => today()->startOfMonth(),
            'period_to' => today(),
            'status' => ClaimStatus::Draft,
            'vat_rate' => '5.00',
            'retention_percent' => '5.00',
        ];
    }
}
