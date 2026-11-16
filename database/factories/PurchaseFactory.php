<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'PB-T-'.fake()->unique()->numberBetween(1, 99999999),
            'supplier_id' => Supplier::factory(),
            'project_id' => Project::factory(),
            'purchase_date' => today(),
            'supplier_invoice_no' => 'S-'.fake()->unique()->numberBetween(1, 99999999),
            'vat_rate' => '5.00',
            'subtotal' => '1000.00',
            'vat_amount' => '50.00',
            'total' => '1050.00',
            'paid_amount' => '0.00',
            'status' => PurchaseStatus::Unpaid,
        ];
    }
}
