<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Scheduling\LocationResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClosingDayRequest;
use App\Http\Resources\ClosingDayResource;
use App\Models\ClosingDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ClosingDayController extends Controller
{
    public function index(Request $request, LocationResolver $locations): AnonymousResourceCollection
    {
        $location = $locations->resolve(
            $request->has('location_id') ? $request->integer('location_id') : null
        );

        return ClosingDayResource::collection(
            ClosingDay::query()
                ->where('location_id', $location->id)
                ->orderBy('date')
                ->get()
        );
    }

    public function store(StoreClosingDayRequest $request, LocationResolver $locations): JsonResponse
    {
        $location = $locations->resolve($request->locationId());

        // Adding the same day twice is treated as a success: the unique index on
        // (location_id, date) keeps a single row and the caller gets it back.
        $closingDay = ClosingDay::query()->updateOrCreate(
            ['location_id' => $location->id, 'date' => $request->closingDate()],
            ['reason' => $request->reason()],
        );

        return ClosingDayResource::make($closingDay)
            ->response()
            ->setStatusCode($closingDay->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(ClosingDay $closingDay): JsonResponse
    {
        $closingDay->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
