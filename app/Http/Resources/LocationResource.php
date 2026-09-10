<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Location
 */
class LocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Location $location */
        $location = $this->resource;

        return [
            'id' => $location->id,
            'name' => $location->name,
            'slug' => $location->slug,
            'timezone' => $location->timezone,
            'slot_duration_minutes' => $location->slot_duration_minutes,
        ];
    }
}
