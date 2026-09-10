<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClosingDayRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'location_id' => ['sometimes', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function locationId(): ?int
    {
        return $this->has('location_id') ? $this->integer('location_id') : null;
    }

    public function closingDate(): string
    {
        return $this->string('date')->toString();
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }
}
