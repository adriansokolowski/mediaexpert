<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LocationFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property int $slot_duration_minutes
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'slot_duration_minutes',
    ];

    private ?DateTimeZone $resolvedTimezone = null;

    protected function casts(): array
    {
        return [
            'slot_duration_minutes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function timezone(): DateTimeZone
    {
        return $this->resolvedTimezone ??= new DateTimeZone($this->timezone);
    }

    /** @return HasMany<BusinessHour, $this> */
    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    /** @return HasMany<ClosingDay, $this> */
    public function closingDays(): HasMany
    {
        return $this->hasMany(ClosingDay::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
