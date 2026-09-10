<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Scheduling\Slots\Slot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Slot
 */
class SlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Slot $slot */
        $slot = $this->resource;

        return [
            'starts_at' => $slot->startsAt->toIso8601String(),
            'ends_at' => $slot->endsAt->toIso8601String(),
        ];
    }
}
