<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Exceptions;

use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * The requested instant is not the start of a slot the location offers, either
 * because it falls outside opening hours, on a closing day, off the 30-minute
 * grid, or in the past.
 */
final class SlotNotBookableException extends SchedulingException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function outsideSchedule(CarbonImmutable $startsAt): self
    {
        return new self(
            sprintf('%s is not an available slot for this location.', $startsAt->toIso8601String()),
            'slot_outside_schedule',
        );
    }

    public static function inThePast(CarbonImmutable $startsAt): self
    {
        return new self(
            sprintf('%s is in the past.', $startsAt->toIso8601String()),
            'slot_in_the_past',
        );
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function httpStatus(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
