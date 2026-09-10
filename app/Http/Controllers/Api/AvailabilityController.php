<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Scheduling\AvailabilityService;
use App\Domain\Scheduling\LocationResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Resources\SlotResource;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __invoke(
        AvailabilityRequest $request,
        LocationResolver $locations,
        AvailabilityService $availability,
    ): JsonResponse {
        $location = $locations->resolve($request->locationId());
        $day = $request->dayFor($location);
        $slots = $availability->forDay($location, $day);

        return response()->json([
            'data' => SlotResource::collection($slots)->resolve(),
            'meta' => [
                'date' => $day->toDateString(),
                'location_id' => $location->id,
                'timezone' => $location->timezone,
                'slot_duration_minutes' => $location->slot_duration_minutes,
                'count' => count($slots),
            ],
        ]);
    }
}
