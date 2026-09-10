<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClosingDay;
use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Polish public holidays as closing days.
 *
 * Closing days live in the database rather than in code so that they can also
 * be managed at runtime through POST /api/closing-days.
 */
class ClosingDaySeeder extends Seeder
{
    private const int YEARS_AHEAD = 2;

    /** @var array<string, string> "m-d" => reason */
    private const array FIXED_HOLIDAYS = [
        '01-01' => 'Nowy Rok',
        '01-06' => 'Trzech Kroli',
        '05-01' => 'Swieto Pracy',
        '05-03' => 'Swieto Konstytucji 3 Maja',
        '08-15' => 'Wniebowziecie NMP',
        '11-01' => 'Wszystkich Swietych',
        '11-11' => 'Narodowe Swieto Niepodleglosci',
        '12-25' => 'Boze Narodzenie',
        '12-26' => 'Drugi dzien Bozego Narodzenia',
    ];

    /** @var array<int, string> days after Easter Sunday => reason */
    private const array MOVABLE_HOLIDAYS = [
        1 => 'Poniedzialek Wielkanocny',
        60 => 'Boze Cialo',
    ];

    public function run(): void
    {
        $locations = Location::query()->get(['id']);

        if ($locations->isEmpty()) {
            return;
        }

        $firstYear = CarbonImmutable::now()->year;
        $rows = [];
        $now = CarbonImmutable::now();

        foreach ($locations as $location) {
            for ($year = $firstYear; $year <= $firstYear + self::YEARS_AHEAD; $year++) {
                foreach ($this->holidaysOf($year) as $date => $reason) {
                    $rows[] = [
                        'location_id' => $location->id,
                        'date' => $date,
                        'reason' => $reason,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Re-running the seeder must not fail on the (location_id, date) unique index.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table((new ClosingDay)->getTable())->insertOrIgnore($chunk);
        }
    }

    /** @return array<string, string> "Y-m-d" => reason */
    private function holidaysOf(int $year): array
    {
        $holidays = [];

        foreach (self::FIXED_HOLIDAYS as $monthDay => $reason) {
            $holidays["{$year}-{$monthDay}"] = $reason;
        }

        $easter = $this->easterSunday($year);

        foreach (self::MOVABLE_HOLIDAYS as $offset => $reason) {
            $holidays[$easter->addDays($offset)->toDateString()] = $reason;
        }

        return $holidays;
    }

    /**
     * Anonymous Gregorian algorithm (Meeus/Jones/Butcher). Implemented inline so
     * the seeder does not depend on the optional calendar extension.
     */
    private function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = ($h + $l - 7 * $m + 114) % 31 + 1;

        return LocalInstant::fromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));
    }
}
