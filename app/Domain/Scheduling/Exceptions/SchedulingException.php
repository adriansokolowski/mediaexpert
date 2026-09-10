<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Exceptions;

use RuntimeException;

abstract class SchedulingException extends RuntimeException
{
    /**
     * Stable, machine-readable counterpart of the message.
     */
    abstract public function errorCode(): string;

    /**
     * HTTP status the API layer should answer with.
     */
    abstract public function httpStatus(): int;
}
