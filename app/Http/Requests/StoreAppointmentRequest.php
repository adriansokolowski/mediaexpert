<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'location_id' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['required', 'string', 'date'],

            // There is no user model: an appointment just needs something to
            // identify the customer by, so at least one of the two is required.
            'customer_name' => ['nullable', 'required_without:customer_email', 'string', 'max:120'],
            'customer_email' => ['nullable', 'required_without:customer_name', 'email', 'max:180'],
        ];
    }

    public function locationId(): ?int
    {
        return $this->has('location_id') ? $this->integer('location_id') : null;
    }

    public function startsAt(Location $location): CarbonImmutable
    {
        return LocalInstant::parse($this->string('starts_at')->toString(), $location->timezone());
    }

    public function customerName(): ?string
    {
        return $this->filled('customer_name') ? $this->string('customer_name')->toString() : null;
    }

    public function customerEmail(): ?string
    {
        return $this->filled('customer_email') ? $this->string('customer_email')->toString() : null;
    }
}
