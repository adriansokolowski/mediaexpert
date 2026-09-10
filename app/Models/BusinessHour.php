<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Scheduling\Slots\TimeInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opening interval of one weekday. Several rows may share a weekday, which
 * is how a midday break would be expressed without a schema change.
 *
 * @property int $location_id
 * @property int $day_of_week ISO-8601: 1 = Monday ... 7 = Sunday
 * @property string $opens_at
 * @property string $closes_at
 */
class BusinessHour extends Model
{
    protected $fillable = [
        'location_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function toInterval(): TimeInterval
    {
        return TimeInterval::fromTimeStrings($this->opens_at, $this->closes_at);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
