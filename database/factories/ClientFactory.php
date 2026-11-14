<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1000000, 9999999);

        return [
            'type' => 'individual',
            'name_ar' => 'عميل '.$n,
            'name_en' => null,
            'phone' => '050'.$n,
            'email' => null,
        ];
    }
}
