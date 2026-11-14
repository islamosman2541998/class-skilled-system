<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteVisit>
 */
class SiteVisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'SV-T-'.fake()->unique()->numberBetween(1, 99999999),
            'client_id' => Client::factory(),
            'scheduled_at' => now()->addDay(),
            'engineer_id' => User::factory(),
            'status' => 'scheduled',
        ];
    }
}
