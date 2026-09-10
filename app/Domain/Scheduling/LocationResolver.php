<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use App\Models\Location;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * The task assumes a single location, so "location_id" is optional everywhere
 * and falls back to the location configured as the default one.
 */
class LocationResolver
{
    private ?Location $default = null;

    public function resolve(?int $id): Location
    {
        if ($id === null) {
            return $this->default();
        }

        return Location::query()->findOrFail($id);
    }

    public function default(): Location
    {
        if ($this->default !== null) {
            return $this->default;
        }

        $slug = Config::string('scheduling.default_location_slug');
        $location = Location::query()->where('slug', $slug)->first();

        if ($location === null) {
            throw new RuntimeException(
                "No location with slug \"{$slug}\" exists. Run `php artisan db:seed` or pass an explicit location_id."
            );
        }

        return $this->default = $location;
    }
}
