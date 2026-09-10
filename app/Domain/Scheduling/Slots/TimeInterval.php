<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Slots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A wall-clock opening interval, e.g. 09:00-17:00, with no date attached.
 */
final readonly class TimeInterval
{
    private function __construct(
        public int $startMinuteOfDay,
        public int $endMinuteOfDay,
    ) {
        if ($endMinuteOfDay <= $startMinuteOfDay) {
            throw new InvalidArgumentException('An opening interval must end after it starts.');
        }
    }

    /**
     * @param  string  $opensAt  "HH:MM" or "HH:MM:SS"
     * @param  string  $closesAt  "HH:MM" or "HH:MM:SS"
     */
    public static function fromTimeStrings(string $opensAt, string $closesAt): self
    {
        return new self(self::toMinutes($opensAt), self::toMinutes($closesAt));
    }

    /**
     * Anchors this interval to a concrete local day.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function on(CarbonImmutable $localDay): array
    {
        // setTime() rather than adding minutes to midnight: opening hours are
        // wall-clock times, and on a DST transition day adding elapsed time
        // would drift the boundary by the offset change (a 06:00 closing time
        // would land on 07:00).
        return [
            $this->at($localDay, $this->startMinuteOfDay),
            $this->at($localDay, $this->endMinuteOfDay),
        ];
    }

    private function at(CarbonImmutable $localDay, int $minuteOfDay): CarbonImmutable
    {
        return $localDay->setTime(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    private static function toMinutes(string $time): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $m) !== 1) {
            throw new InvalidArgumentException("Unsupported time format: {$time}");
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        if ($hours > 24 || $minutes > 59) {
            throw new InvalidArgumentException("Time out of range: {$time}");
        }

        return $hours * 60 + $minutes;
    }
}
