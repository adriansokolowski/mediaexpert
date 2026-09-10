<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CalendarDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A full day on which the location does not take appointments, regardless of
 * the weekday's regular opening hours.
 *
 * @property int $location_id
 * @property CarbonImmutable $date
 * @property string|null $reason
 */
class ClosingDay extends Model
{
    protected $fillable = [
        'location_id',
        'date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => CalendarDate::class,
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
