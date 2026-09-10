<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'location_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function locationId(): ?int
    {
        return $this->has('location_id') ? $this->integer('location_id') : null;
    }

    /**
     * The requested day, anchored in the location's timezone so that the
     * calendar date means the same thing to the client and to the schedule.
     */
    public function dayFor(Location $location): CarbonImmutable
    {
        return LocalInstant::fromFormat(
            'Y-m-d H:i:s',
            $this->string('date')->toString().' 00:00:00',
            $location->timezone(),
        );
    }
}
