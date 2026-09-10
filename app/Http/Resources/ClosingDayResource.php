<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClosingDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClosingDay
 */
class ClosingDayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ClosingDay $closingDay */
        $closingDay = $this->resource;

        return [
            'id' => $closingDay->id,
            'location_id' => $closingDay->location_id,
            'date' => $closingDay->date->toDateString(),
            'reason' => $closingDay->reason,
        ];
    }
}
