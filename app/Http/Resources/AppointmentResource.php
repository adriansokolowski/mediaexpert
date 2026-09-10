<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->resource;

        // Rendered in the location's timezone; the relation is always eager
        // loaded by the callers, so listing many rows stays at one query.
        $timezone = $appointment->location->timezone();

        return [
            'id' => $appointment->public_id,
            'location' => [
                'id' => $appointment->location_id,
                'slug' => $appointment->location->slug,
                'timezone' => $appointment->location->timezone,
            ],
            'starts_at' => $appointment->starts_at->setTimezone($timezone)->toIso8601String(),
            'ends_at' => $appointment->ends_at->setTimezone($timezone)->toIso8601String(),
            'status' => $appointment->status->value,
            'customer' => [
                'name' => $appointment->customer_name,
                'email' => $appointment->customer_email,
            ],
            'cancelled_at' => $appointment->cancelled_at?->setTimezone($timezone)->toIso8601String(),
            'created_at' => $appointment->created_at?->setTimezone($timezone)->toIso8601String(),
        ];
    }
}
