<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A quotation row with consistent totals (no items).
 *
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title_ar' => 'عرض '.fake()->unique()->numberBetween(1, 9999999),
            'issue_date' => today(),
            'valid_until' => today()->addDays(30),
            'assigned_to' => User::factory(),
            'discount_value' => '0',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Quotation $quotation) {
            $quotation->number ??= 'QT-T-'.fake()->unique()->numberBetween(1, 99999999);
            $quotation->status ??= QuotationStatus::Draft;
            $quotation->vat_rate ??= '5.00';
            $quotation->total ??= '0';
            $quotation->subtotal ??= $quotation->total;
            $quotation->taxable_amount ??= $quotation->total;
        });
    }
}
