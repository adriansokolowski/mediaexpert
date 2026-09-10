<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Domain\Scheduling\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $location_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $active_starts_at
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property AppointmentStatus $status
 * @property CarbonImmutable|null $cancelled_at
 * @property-read Location $location
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'location_id',
        'starts_at',
        'ends_at',
        'active_starts_at',
        'customer_name',
        'customer_email',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'active_starts_at' => UtcDateTime::class,
            'cancelled_at' => UtcDateTime::class,
            'status' => AppointmentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $appointment): void {
            $appointment->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @param  Builder<$this>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Booked);
    }
}
