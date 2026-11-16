<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1000000, 9999999);

        return [
            'type' => 'supplier',
            'name_ar' => 'مورد '.$n,
            'name_en' => null,
            'phone' => '050'.$n,
            'is_active' => true,
        ];
    }

    public function subcontractor(): static
    {
        return $this->state(fn () => ['type' => 'subcontractor']);
    }
}
