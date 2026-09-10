<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
            'timezone' => 'Europe/Warsaw',
            'slot_duration_minutes' => 30,
        ];
    }
}
