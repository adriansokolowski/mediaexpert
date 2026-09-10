<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Exceptions;

use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Raised when the database rejected the write because another active
 * appointment already occupies the slot. This is the loser of a race, not a
 * pre-check failure.
 */
final class SlotAlreadyBookedException extends SchedulingException
{
    public function __construct(CarbonImmutable $startsAt, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf('The slot starting at %s is already taken.', $startsAt->toIso8601String()),
            0,
            $previous,
        );
    }

    public function errorCode(): string
    {
        return 'slot_already_booked';
    }

    public function httpStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
