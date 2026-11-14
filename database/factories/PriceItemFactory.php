<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceItem>
 */
class PriceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'description_ar' => 'بند '.fake()->unique()->numberBetween(1, 9999999),
            'unit_id' => fn () => Lookup::query()->ofType(LookupType::Unit)->value('id') ?? Lookup::factory()->create(['type' => LookupType::Unit])->id,
            'default_price' => '0',
            'is_active' => true,
        ];
    }
}
