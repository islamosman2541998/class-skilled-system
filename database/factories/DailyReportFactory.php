<?php

namespace Database\Factories;

use App\Models\DailyReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReport>
 */
class DailyReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'report_date' => today(),
            'engineer_id' => User::factory(),
            'work_done' => 'أعمال الموقع',
            'labour_count' => 6,
            'has_obstacles' => false,
        ];
    }
}
