<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Enums;

enum AppointmentStatus: string
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return $this === self::Booked;
    }
}
