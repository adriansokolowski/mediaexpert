<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Scheduling\Slots\TimeInterval;
use App\Support\LocalInstant;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeIntervalTest extends TestCase
{
    public function test_it_reads_minutes_from_a_time_string(): void
    {
        $interval = TimeInterval::fromTimeStrings('10:00:00', '14:20:00');

        $this->assertSame(600, $interval->startMinuteOfDay);
        $this->assertSame(860, $interval->endMinuteOfDay);
    }

    public function test_it_accepts_times_without_seconds(): void
    {
        $interval = TimeInterval::fromTimeStrings('9:05', '17:00');

        $this->assertSame(545, $interval->startMinuteOfDay);
        $this->assertSame(1020, $interval->endMinuteOfDay);
    }

    public function test_it_anchors_itself_to_a_local_day(): void
    {
        $timezone = new DateTimeZone('Europe/Warsaw');
        $day = LocalInstant::fromFormat('Y-m-d H:i:s', '2026-09-12 00:00:00', $timezone);

        [$opensAt, $closesAt] = TimeInterval::fromTimeStrings('10:00', '14:20')->on($day);

        $this->assertSame('2026-09-12T10:00:00+02:00', $opensAt->toIso8601String());
        $this->assertSame('2026-09-12T14:20:00+02:00', $closesAt->toIso8601String());
    }

    public function test_it_rejects_an_interval_that_does_not_move_forward(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeInterval::fromTimeStrings('17:00', '09:00');
    }

    #[DataProvider('malformedTimes')]
    public function test_it_rejects_malformed_times(string $time): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimeInterval::fromTimeStrings($time, '23:00');
    }

    /** @return iterable<string, array{string}> */
    public static function malformedTimes(): iterable
    {
        yield 'not a time' => ['morning'];
        yield 'hour out of range' => ['25:00'];
        yield 'minute out of range' => ['10:75'];
        yield 'missing minutes' => ['10'];
    }
}
