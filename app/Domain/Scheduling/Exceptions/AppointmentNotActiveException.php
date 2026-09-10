<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * A cancelled appointment cannot be moved to another slot; a new one has to be
 * created instead.
 */
final class AppointmentNotActiveException extends SchedulingException
{
    public function __construct()
    {
        parent::__construct('This appointment has been cancelled and can no longer be modified.');
    }

    public function errorCode(): string
    {
        return 'appointment_not_active';
    }

    public function httpStatus(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
