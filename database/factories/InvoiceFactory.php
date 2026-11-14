<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'client_id' => fn (array $attributes) => Project::query()->find($attributes['project_id'])->client_id,
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'status' => InvoiceStatus::Draft,
            'vat_rate' => '5.00',
            'subtotal' => '1000.00',
            'vat_amount' => '50.00',
            'total' => '1050.00',
            'paid_amount' => '0.00',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'number' => 'INV-T-'.fake()->unique()->numberBetween(1, 99999999),
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
