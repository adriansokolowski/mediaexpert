<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'string', 'date'],
        ];
    }

    public function startsAt(Location $location): CarbonImmutable
    {
        return LocalInstant::parse($this->string('starts_at')->toString(), $location->timezone());
    }
}
