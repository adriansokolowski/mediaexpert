<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessHour;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * ISO-8601 weekday => opening intervals. Sunday (7) is absent, which is how
     * a closed weekday is expressed. A weekday may list several intervals, so a
     * midday break would not need a schema change.
     *
     * @var array<int, list<array{string, string}>>
     */
    private const array OPENING_HOURS = [
        1 => [['09:00', '17:00']],
        2 => [['09:00', '17:00']],
        3 => [['09:00', '17:00']],
        4 => [['09:00', '17:00']],
        5 => [['09:00', '17:00']],
        6 => [['10:00', '14:20']],
    ];

    public function run(): void
    {
        $location = Location::query()->updateOrCreate(
            ['slug' => (string) config('scheduling.default_location_slug')],
            [
                'name' => 'Main office',
                'timezone' => (string) config('scheduling.timezone'),
                'slot_duration_minutes' => (int) config('scheduling.slot_duration_minutes'),
            ],
        );

        foreach (self::OPENING_HOURS as $isoWeekday => $intervals) {
            foreach ($intervals as [$opensAt, $closesAt]) {
                BusinessHour::query()->updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'day_of_week' => $isoWeekday,
                        'opens_at' => $opensAt.':00',
                    ],
                    ['closes_at' => $closesAt.':00'],
                );
            }
        }
    }
}
