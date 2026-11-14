<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\Lead;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs the LookupSeeder lists (lead statuses with codes).
 *
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;

        return [
            'number' => 'LD-T-'.str_pad((string) self::$sequence, 6, '0', STR_PAD_LEFT).'-'.fake()->unique()->numberBetween(1, 99999999),
            'contact_name' => 'عميل محتمل '.self::$sequence,
            'contact_phone' => '055'.fake()->unique()->numberBetween(1000000, 9999999),
            'service_id' => fn () => Lookup::query()->ofType(LookupType::Service)->value('id')
                ?? Lookup::factory()->create(['type' => LookupType::Service])->id,
            'source_id' => fn () => Lookup::query()->ofType(LookupType::LeadSource)->value('id'),
            'status_id' => fn () => Lead::statusIdFor('new') ?: Lookup::factory()->create(['type' => LookupType::LeadStatus, 'code' => 'new'])->id,
        ];
    }
}
