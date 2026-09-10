<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Scheduling\Enums\AppointmentStatus;
use App\Models\Location;
use App\Support\LocalInstant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAppointmentRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 100;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'location_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(AppointmentStatus::class)],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'customer_email' => ['sometimes', 'email', 'max:180'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    public function locationId(): ?int
    {
        return $this->has('location_id') ? $this->integer('location_id') : null;
    }

    public function status(): ?AppointmentStatus
    {
        return $this->filled('status')
            ? AppointmentStatus::from($this->string('status')->toString())
            : null;
    }

    /** Inclusive lower bound, as the first instant of that local day in UTC. */
    public function from(Location $location): ?CarbonImmutable
    {
        return $this->localDay('from', $location)?->utc();
    }

    /** Exclusive upper bound: the first instant of the day after "to". */
    public function until(Location $location): ?CarbonImmutable
    {
        return $this->localDay('to', $location)?->addDay()->utc();
    }

    public function customerEmail(): ?string
    {
        return $this->filled('customer_email') ? $this->string('customer_email')->toString() : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }

    private function localDay(string $key, Location $location): ?CarbonImmutable
    {
        if (! $this->filled($key)) {
            return null;
        }

        return LocalInstant::fromFormat(
            'Y-m-d H:i:s',
            $this->string($key)->toString().' 00:00:00',
            $location->timezone(),
        );
    }
}
