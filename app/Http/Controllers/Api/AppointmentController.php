<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Scheduling\AppointmentBooker;
use App\Domain\Scheduling\LocationResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AppointmentController extends Controller
{
    public function index(IndexAppointmentRequest $request, LocationResolver $locations): AnonymousResourceCollection
    {
        $location = $locations->resolve($request->locationId());

        $query = Appointment::query()
            ->with('location')
            ->where('location_id', $location->id)
            // Matches the leading columns of appointments_location_starts_idx;
            // id breaks ties so the keyset cursor is deterministic.
            ->orderBy('starts_at')
            ->orderBy('id');

        $query->when($request->status(), fn ($q, $status) => $q->where('status', $status));
        $query->when($request->from($location), fn ($q, $from) => $q->where('starts_at', '>=', $from));
        $query->when($request->until($location), fn ($q, $until) => $q->where('starts_at', '<', $until));
        $query->when($request->customerEmail(), fn ($q, $email) => $q->where('customer_email', $email));

        // Keyset pagination: no COUNT(*) and no growing OFFSET, so page 10 000
        // costs the same as page 1.
        return AppointmentResource::collection(
            $query->cursorPaginate($request->perPage())->withQueryString()
        );
    }

    public function store(
        StoreAppointmentRequest $request,
        LocationResolver $locations,
        AppointmentBooker $booker,
    ): JsonResponse {
        $location = $locations->resolve($request->locationId());

        $appointment = $booker->book(
            $location,
            $request->startsAt($location),
            $request->customerName(),
            $request->customerEmail(),
        );

        return AppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return AppointmentResource::make($appointment->loadMissing('location'));
    }

    public function reschedule(
        RescheduleAppointmentRequest $request,
        Appointment $appointment,
        AppointmentBooker $booker,
    ): AppointmentResource {
        $appointment->loadMissing('location');

        return AppointmentResource::make(
            $booker->reschedule($appointment, $request->startsAt($appointment->location))
        );
    }

    public function destroy(Appointment $appointment, AppointmentBooker $booker): AppointmentResource
    {
        $appointment->loadMissing('location');

        return AppointmentResource::make($booker->cancel($appointment));
    }
}
